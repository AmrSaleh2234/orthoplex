<?php

namespace Modules\Analytics\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Analytics\app\Models\LoginEvent;
use Modules\Analytics\app\Models\LoginDaily;
use Modules\User\Models\CentralUser;
use Modules\Tenant\app\Models\Tenant;

class LoginAnalyticsService
{
    /**
     * Record a login event
     */
    public function recordLoginEvent(array $data): LoginEvent
    {
        return LoginEvent::create([
            'central_user_id' => $data['central_user_id'],
            'tenant_id' => $data['tenant_id'] ?? null,
            'global_user_id' => $data['global_user_id'],
            'ip_address' => $data['ip_address'],
            'user_agent' => $data['user_agent'] ?? null,
            'login_method' => $data['login_method'] ?? 'password',
            'two_factor_used' => $data['two_factor_used'] ?? false,
            'success' => $data['success'] ?? true,
            'failure_reason' => $data['failure_reason'] ?? null,
            'login_at' => $data['login_at'] ?? now(),
            'device_info' => $data['device_info'] ?? null,
            'location_info' => $data['location_info'] ?? null,
        ]);
    }

    /**
     * Record a successful login
     */
    public function recordSuccessfulLogin(
        CentralUser $user, 
        ?Tenant $tenant = null, 
        ?Request $request = null,
        array $additionalData = []
    ): LoginEvent {
        $deviceInfo = $this->extractDeviceInfo($request);
        
        $loginData = array_merge([
            'central_user_id' => $user->id,
            'tenant_id' => $tenant?->id,
            'global_user_id' => $user->global_id,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'login_method' => 'password',
            'two_factor_used' => $user->two_factor_enabled,
            'success' => true,
            'login_at' => now(),
            'device_info' => $deviceInfo,
            'location_info' => $this->getLocationInfo($request?->ip()),
        ], $additionalData);

        $loginEvent = $this->recordLoginEvent($loginData);
        
        // Update daily statistics
        if ($tenant) {
            $this->updateDailyStats($tenant->id, $loginData);
        }

        return $loginEvent;
    }

    /**
     * Record a failed login attempt
     */
    public function recordFailedLogin(
        string $email,
        ?Tenant $tenant = null,
        ?Request $request = null,
        string $reason = 'Invalid credentials'
    ): LoginEvent {
        // Try to find central user
        $user = CentralUser::where('email', $email)->first();
        
        $loginData = [
            'central_user_id' => $user?->id,
            'tenant_id' => $tenant?->id,
            'global_user_id' => $user?->global_id,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'login_method' => 'password',
            'two_factor_used' => false,
            'success' => false,
            'failure_reason' => $reason,
            'login_at' => now(),
            'device_info' => $this->extractDeviceInfo($request),
        ];

        $loginEvent = $this->recordLoginEvent($loginData);
        
        // Update daily statistics for failed attempts
        if ($tenant) {
            $this->updateDailyStats($tenant->id, $loginData);
        }

        return $loginEvent;
    }

    /**
     * Record magic link login
     */
    public function recordMagicLinkLogin(
        CentralUser $user,
        ?Tenant $tenant = null,
        ?Request $request = null
    ): LoginEvent {
        return $this->recordSuccessfulLogin($user, $tenant, $request, [
            'login_method' => 'magic_link',
            'two_factor_used' => false,
        ]);
    }

    /**
     * Record 2FA login completion
     */
    public function recordTwoFactorLogin(
        CentralUser $user,
        ?Tenant $tenant = null,
        ?Request $request = null
    ): LoginEvent {
        return $this->recordSuccessfulLogin($user, $tenant, $request, [
            'login_method' => 'password',
            'two_factor_used' => true,
        ]);
    }

    /**
     * Update daily login statistics
     */
    protected function updateDailyStats(string $tenantId, array $loginData): void
    {
        $date = Carbon::parse($loginData['login_at'])->toDateString();
        
        $daily = LoginDaily::firstOrNew([
            'tenant_id' => $tenantId,
            'date' => $date,
        ]);

        // Initialize if new record
        if (!$daily->exists) {
            $daily->login_count = 0;
            $daily->successful_logins = 0;
            $daily->failed_logins = 0;
            $daily->unique_users = 0;
            $daily->two_factor_logins = 0;
            $daily->magic_link_logins = 0;
            $daily->password_logins = 0;
        }

        // Update counters
        $daily->login_count++;
        
        if ($loginData['success']) {
            $daily->successful_logins++;
            
            // Count login methods
            switch ($loginData['login_method']) {
                case 'magic_link':
                    $daily->magic_link_logins++;
                    break;
                case 'password':
                    $daily->password_logins++;
                    if ($loginData['two_factor_used']) {
                        $daily->two_factor_logins++;
                    }
                    break;
            }

            // Update unique users count (expensive query, consider caching)
            if ($loginData['central_user_id']) {
                $uniqueUsersToday = LoginEvent::forTenant($tenantId)
                    ->successful()
                    ->whereDate('login_at', $date)
                    ->distinct('central_user_id')
                    ->count('central_user_id');
                
                $daily->unique_users = $uniqueUsersToday;
            }
        } else {
            $daily->failed_logins++;
        }

        $daily->save();
    }

    /**
     * Get login analytics for a tenant
     */
    public function getTenantLoginAnalytics(string $tenantId, array $options = []): array
    {
        $startDate = $options['start_date'] ?? now()->subDays(30);
        $endDate = $options['end_date'] ?? now();

        // Get daily stats
        $dailyStats = LoginDaily::forTenant($tenantId)
            ->dateRange($startDate, $endDate)
            ->orderBy('date')
            ->get();

        // Get recent login events
        $recentLogins = LoginEvent::forTenant($tenantId)
            ->successful()
            ->dateRange($startDate, $endDate)
            ->with(['centralUser', 'tenant'])
            ->orderBy('login_at', 'desc')
            ->limit($options['recent_limit'] ?? 50)
            ->get();

        // Calculate totals
        $totals = [
            'total_logins' => $dailyStats->sum('login_count'),
            'successful_logins' => $dailyStats->sum('successful_logins'),
            'failed_logins' => $dailyStats->sum('failed_logins'),
            'unique_users' => LoginEvent::forTenant($tenantId)
                ->successful()
                ->dateRange($startDate, $endDate)
                ->distinct('central_user_id')
                ->count('central_user_id'),
            'two_factor_usage' => $dailyStats->sum('two_factor_logins'),
            'magic_link_usage' => $dailyStats->sum('magic_link_logins'),
        ];

        $totals['success_rate'] = $totals['total_logins'] > 0 
            ? round(($totals['successful_logins'] / $totals['total_logins']) * 100, 2) 
            : 0;

        $totals['two_factor_rate'] = $totals['successful_logins'] > 0 
            ? round(($totals['two_factor_usage'] / $totals['successful_logins']) * 100, 2) 
            : 0;

        return [
            'daily_stats' => $dailyStats,
            'recent_logins' => $recentLogins,
            'totals' => $totals,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ];
    }

    /**
     * Get user login analytics
     */
    public function getUserLoginAnalytics(string $centralUserId, array $options = []): array
    {
        $startDate = $options['start_date'] ?? now()->subDays(30);
        $endDate = $options['end_date'] ?? now();

        $loginEvents = LoginEvent::forUser($centralUserId)
            ->dateRange($startDate, $endDate)
            ->with(['tenant'])
            ->orderBy('login_at', 'desc')
            ->get();

        $successful = $loginEvents->where('success', true);
        $failed = $loginEvents->where('success', false);

        return [
            'total_logins' => $loginEvents->count(),
            'successful_logins' => $successful->count(),
            'failed_logins' => $failed->count(),
            'success_rate' => $loginEvents->count() > 0 
                ? round(($successful->count() / $loginEvents->count()) * 100, 2) 
                : 0,
            'tenants_accessed' => $successful->pluck('tenant_id')->unique()->count(),
            'login_methods' => $successful->groupBy('login_method')->map->count(),
            'recent_logins' => $loginEvents->take($options['recent_limit'] ?? 20),
        ];
    }

    /**
     * Extract device information from request
     */
    protected function extractDeviceInfo(?Request $request): ?array
    {
        if (!$request) {
            return null;
        }

        $userAgent = $request->userAgent();
        if (!$userAgent) {
            return null;
        }

        // Basic device detection (consider using a proper library like jenssegers/agent)
        $deviceInfo = [
            'user_agent' => $userAgent,
            'is_mobile' => $this->isMobile($userAgent),
            'is_tablet' => $this->isTablet($userAgent),
            'is_desktop' => !$this->isMobile($userAgent) && !$this->isTablet($userAgent),
        ];

        // Extract browser and platform info (basic implementation)
        $deviceInfo['browser'] = $this->detectBrowser($userAgent);
        $deviceInfo['platform'] = $this->detectPlatform($userAgent);

        return $deviceInfo;
    }

    /**
     * Get location information from IP address
     */
    protected function getLocationInfo(?string $ip): ?array
    {
        if (!$ip || $ip === '127.0.0.1') {
            return null;
        }

        // TODO: Implement IP geolocation service integration
        // For now, return basic info
        return [
            'ip' => $ip,
            'country' => null,
            'city' => null,
        ];
    }

    /**
     * Basic mobile detection
     */
    protected function isMobile(string $userAgent): bool
    {
        return preg_match('/Mobile|Android|iPhone|iPad/', $userAgent) === 1;
    }

    /**
     * Basic tablet detection
     */
    protected function isTablet(string $userAgent): bool
    {
        return preg_match('/iPad|Tablet/', $userAgent) === 1;
    }

    /**
     * Basic browser detection
     */
    protected function detectBrowser(string $userAgent): ?string
    {
        if (strpos($userAgent, 'Chrome') !== false) return 'Chrome';
        if (strpos($userAgent, 'Firefox') !== false) return 'Firefox';
        if (strpos($userAgent, 'Safari') !== false) return 'Safari';
        if (strpos($userAgent, 'Edge') !== false) return 'Edge';
        
        return 'Unknown';
    }

    /**
     * Basic platform detection
     */
    protected function detectPlatform(string $userAgent): ?string
    {
        if (strpos($userAgent, 'Windows') !== false) return 'Windows';
        if (strpos($userAgent, 'Mac') !== false) return 'macOS';
        if (strpos($userAgent, 'Linux') !== false) return 'Linux';
        if (strpos($userAgent, 'Android') !== false) return 'Android';
        if (strpos($userAgent, 'iOS') !== false) return 'iOS';
        
        return 'Unknown';
    }
}
