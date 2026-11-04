<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\SocialFeed\Http\Controllers\Api;

use Cycle\Database\DatabaseInterface;
use Witals\Framework\Application;
use Witals\Framework\Http\Request;
use Witals\Framework\Http\Response;

class PomodoroController
{
    private string $table;

    public function __construct(
        private Application $app,
        private DatabaseInterface $db,
    ) {
        $prefix = (string) ($this->app->config('social-feed.table_prefix', 'pw_') ?: 'pw_');
        $this->table = $prefix . 'user_pomodoro';
    }

    public function handle(Request $request): Response
    {
        $user = function_exists('auth_user') ? auth_user() : null;
        $userId = is_array($user) ? (string) ($user['user_id'] ?? $user['id'] ?? '') : '';
        if ($userId === '') {
            return Response::json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $method = strtoupper($request->method());

        try {
            if ($method === 'GET') {
                $row = $this->get($userId);
                return Response::json(['success' => true, 'data' => $row]);
            }
            if ($method === 'PUT' || $method === 'POST') {
                $input = $request->getJson() ?? [];
                if (!is_array($input)) {
                    $input = [];
                }
                $row = $this->update($userId, $input);
                return Response::json(['success' => true, 'data' => $row]);
            }
            if ($method === 'POST' && str_contains($request->path(), '/session/complete')) {
                $row = $this->completeSession($userId);
                return Response::json(['success' => true, 'data' => $row]);
            }
            if ($method === 'POST' && str_contains($request->path(), '/reset-today')) {
                $row = $this->resetToday($userId);
                return Response::json(['success' => true, 'data' => $row]);
            }
        } catch (\Throwable $e) {
            return Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }

        return Response::json(['success' => false, 'error' => 'Method not allowed'], 405);
    }

    private function get(string $userId): array
    {
        $row = $this->db->select('*')->from($this->table)->where('user_id', $userId)->run()->fetch();
        if ($row === false) {
            $now = date('Y-m-d H:i:s');
            $this->db->insert($this->table)->values([
                'user_id' => $userId,
                'work_minutes' => 25,
                'short_break_minutes' => 5,
                'long_break_minutes' => 15,
                'long_break_interval' => 4,
                'auto_start_breaks' => 0,
                'auto_start_pomodoros' => 0,
                'sound_enabled' => 1,
                'sessions_completed_today' => 0,
                'total_sessions' => 0,
                'total_minutes' => 0,
                'current_state' => 'work',
                'current_cycle' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ])->run();
            $row = $this->db->select('*')->from($this->table)->where('user_id', $userId)->run()->fetch();
        }
        $row = is_array($row) ? $row : (array)$row;
        return $this->normalize($row);
    }

    private function update(string $userId, array $input): array
    {
        $this->get($userId);
        $now = date('Y-m-d H:i:s');
        $data = [];
        foreach (['work_minutes', 'short_break_minutes', 'long_break_minutes', 'long_break_interval', 'current_state', 'current_cycle'] as $key) {
            if (array_key_exists($key, $input)) {
                if (is_numeric($input[$key])) {
                    $data[$key] = (int)$input[$key];
                } elseif (is_string($input[$key])) {
                    $data[$key] = $input[$key];
                }
            }
        }
        foreach (['auto_start_breaks', 'auto_start_pomodoros', 'sound_enabled'] as $key) {
            if (array_key_exists($key, $input)) {
                $data[$key] = $input[$key] ? 1 : 0;
            }
        }
        $data['updated_at'] = $now;
        if ($data !== []) {
            $this->db->update($this->table)->set($data)->where('user_id', $userId)->run();
        }
        $row = $this->db->select('*')->from($this->table)->where('user_id', $userId)->run()->fetch();
        $row = is_array($row) ? $row : (array)$row;
        return $this->normalize($row);
    }

    private function completeSession(string $userId): array
    {
        $row = $this->get($userId);
        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');
        $last = $row['lastSessionAt'] ?? $row['last_session_at'] ?? null;
        $sessionsToday = (int)($row['sessionsCompletedToday'] ?? $row['sessions_completed_today'] ?? 0);
        if (is_string($last) && str_starts_with($last, $today)) {
            $sessionsToday += 1;
        } else {
            $sessionsToday = 1;
        }
        $workMinutes = (int)($row['workMinutes'] ?? $row['work_minutes'] ?? 25);
        $this->db->update($this->table)->set([
            'sessions_completed_today' => $sessionsToday,
            'total_sessions' => (int)($row['totalSessions'] ?? $row['total_sessions'] ?? 0) + 1,
            'total_minutes' => (int)($row['totalMinutes'] ?? $row['total_minutes'] ?? 0) + $workMinutes,
            'last_session_at' => $now,
            'updated_at' => $now,
            'current_cycle' => (((int)($row['currentCycle'] ?? $row['current_cycle'] ?? 1)) % max(1, (int)($row['longBreakInterval'] ?? $row['long_break_interval'] ?? 4))) + 1,
        ])->where('user_id', $userId)->run();
        $row = $this->db->select('*')->from($this->table)->where('user_id', $userId)->run()->fetch();
        $row = is_array($row) ? $row : (array)$row;
        return $this->normalize($row);
    }

    private function resetToday(string $userId): array
    {
        $now = date('Y-m-d H:i:s');
        $this->db->update($this->table)->set([
            'sessions_completed_today' => 0,
            'updated_at' => $now,
        ])->where('user_id', $userId)->run();
        $row = $this->db->select('*')->from($this->table)->where('user_id', $userId)->run()->fetch();
        $row = is_array($row) ? $row : (array)$row;
        return $this->normalize($row);
    }

    private function normalize(array $row): array
    {
        return [
            'workMinutes' => (int)($row['work_minutes'] ?? 25),
            'shortBreakMinutes' => (int)($row['short_break_minutes'] ?? 5),
            'longBreakMinutes' => (int)($row['long_break_minutes'] ?? 15),
            'longBreakInterval' => (int)($row['long_break_interval'] ?? 4),
            'autoStartBreaks' => ((int)($row['auto_start_breaks'] ?? 0)) === 1,
            'autoStartPomodoros' => ((int)($row['auto_start_pomodoros'] ?? 0)) === 1,
            'soundEnabled' => ((int)($row['sound_enabled'] ?? 1)) === 1,
            'sessionsCompletedToday' => (int)($row['sessions_completed_today'] ?? 0),
            'totalSessions' => (int)($row['total_sessions'] ?? 0),
            'totalMinutes' => (int)($row['total_minutes'] ?? 0),
            'currentState' => (string)($row['current_state'] ?? 'work'),
            'currentCycle' => (int)($row['current_cycle'] ?? 1),
            'lastSessionAt' => $row['last_session_at'] ?? null,
        ];
    }
}
