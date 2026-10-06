<?php

namespace App\Services\Mobile;

use App\Presenters\RescuerRadioPresenter;
use App\Queries\RescuerRadioFeedQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RescuerRadioWorkflow
{
    public function __construct(private \App\Services\Mobile\RescuerMobileSupport $support, private RescuerRadioPresenter $radioPresenter, private RescuerRadioFeedQuery $feedQuery) {}



    public function startRadioTransmission(Request $request): JsonResponse
    {
        if (! Schema::hasTable('responder_communication_logs')) {
            return $this->missingTableResponse('responder_communication_logs');
        }

        $validated = $request->validate([
            'channel' => ['required', Rule::in(['command', 'team', 'event'])],
            'assignment_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $responder = $this->support->responderForUser($user);

        if (! $responder) {
            return response()->json([
                'message' => 'Rescuer profile was not found for this account.',
            ], 404);
        }

        $transmissionId = 'PTT-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(5));
        $message = $this->radioMessage($request, $responder, [
            'type' => 'ptt_start',
            'channel' => $validated['channel'],
            'transmission_id' => $transmissionId,
            'assignment_id' => $validated['assignment_id'] ?? null,
            'audio_status' => 'metadata_only_until_live_audio_server_is_connected',
        ]);

        $this->insertRadioLog($responder, $message);

        return response()->json([
            'message' => 'PTT transmission started.',
            'data' => [
                'transmission_id' => $transmissionId,
                'active_transmission' => $this->feedQuery->activeRadioTransmission($responder),
            ],
        ], 201);
    }

    public function heartbeatRadioTransmission(Request $request): JsonResponse
    {
        if (! Schema::hasTable('responder_communication_logs')) {
            return $this->missingTableResponse('responder_communication_logs');
        }

        $validated = $request->validate([
            'channel' => ['required', Rule::in(['command', 'team', 'event'])],
            'transmission_id' => ['required', 'string', 'max:80'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
        ]);

        $responder = $this->support->responderForUser($request->user());

        if (! $responder) {
            return response()->json([
                'message' => 'Rescuer profile was not found for this account.',
            ], 404);
        }

        $this->insertRadioLog($responder, $this->radioMessage($request, $responder, [
            'type' => 'ptt_heartbeat',
            'channel' => $validated['channel'],
            'transmission_id' => $validated['transmission_id'],
            'duration_seconds' => $validated['duration_seconds'] ?? 0,
            'audio_status' => 'metadata_only_until_live_audio_server_is_connected',
        ]));

        return response()->json([
            'message' => 'PTT heartbeat saved.',
            'data' => [
                'active_transmission' => $this->feedQuery->activeRadioTransmission($responder),
            ],
        ]);
    }

    public function stopRadioTransmission(Request $request): JsonResponse
    {
        if (! Schema::hasTable('responder_communication_logs')) {
            return $this->missingTableResponse('responder_communication_logs');
        }

        $validated = $request->validate([
            'channel' => ['required', Rule::in(['command', 'team', 'event'])],
            'transmission_id' => ['required', 'string', 'max:80'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
        ]);

        $responder = $this->support->responderForUser($request->user());

        if (! $responder) {
            return response()->json([
                'message' => 'Rescuer profile was not found for this account.',
            ], 404);
        }

        $this->insertRadioLog($responder, $this->radioMessage($request, $responder, [
            'type' => 'ptt_end',
            'channel' => $validated['channel'],
            'transmission_id' => $validated['transmission_id'],
            'duration_seconds' => $validated['duration_seconds'] ?? 0,
            'audio_status' => 'metadata_only_until_live_audio_server_is_connected',
        ]));

        return response()->json([
            'message' => 'PTT transmission stopped.',
            'data' => [
                'active_transmission' => $this->feedQuery->activeRadioTransmission($responder),
            ],
        ]);
    }

    public function storeRadioClip(Request $request): JsonResponse
    {
        if (! Schema::hasTable('responder_communication_logs')) {
            return $this->missingTableResponse('responder_communication_logs');
        }

        $validated = $request->validate([
            'channel' => ['required', Rule::in(['command', 'team', 'event'])],
            'assignment_id' => ['nullable', 'integer'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:600'],
            'audio' => ['required', 'file', 'max:15360'],
        ], [
            'audio.required' => 'Record a voice message before sending.',
            'audio.file' => 'The voice message file is invalid.',
        ]);

        $responder = $this->support->responderForUser($request->user());

        if (! $responder) {
            return response()->json([
                'message' => 'Rescuer profile was not found for this account.',
            ], 404);
        }

        $file = $request->file('audio');
        $transmissionId = 'PTT-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(5));
        $extension = strtolower($file->getClientOriginalExtension() ?: 'm4a');
        $safeExtension = in_array($extension, ['m4a', 'mp4', 'aac', 'wav', 'mp3', 'webm'], true) ? $extension : 'm4a';
        $path = $file->storeAs(
            'radio-ptt/' . now()->format('Y/m/d'),
            $transmissionId . '.' . $safeExtension,
            'public'
        );

        $message = $this->radioMessage($request, $responder, [
            'type' => 'ptt_audio',
            'channel' => $validated['channel'],
            'transmission_id' => $transmissionId,
            'assignment_id' => $validated['assignment_id'] ?? null,
            'duration_seconds' => (int) ($validated['duration_seconds'] ?? 0),
            'audio_path' => $path,
            'audio_status' => 'stored_voice_clip',
        ]);

        $communicationId = $this->insertRadioLog($responder, $message);
        $log = DB::table('responder_communication_logs')
            ->where('communication_id', $communicationId)
            ->first();

        return response()->json([
            'message' => 'Voice transmission sent.',
            'data' => [
                'log' => $log ? $this->radioPresenter->log($log) : null,
                'active_transmission' => $this->feedQuery->activeRadioTransmission($responder),
            ],
        ], 201);
    }

    public function storeRadioSignal(Request $request): JsonResponse
    {
        if (! Schema::hasTable('responder_communication_logs')) {
            return $this->missingTableResponse('responder_communication_logs');
        }

        $validated = $request->validate([
            'channel' => ['required', Rule::in(['command', 'team', 'event'])],
            'signal' => ['required', Rule::in(['Copy', 'Need backup', 'On-scene', 'Clear'])],
        ]);

        $responder = $this->support->responderForUser($request->user());

        if (! $responder) {
            return response()->json([
                'message' => 'Rescuer profile was not found for this account.',
            ], 404);
        }

        $this->insertRadioLog($responder, $this->radioMessage($request, $responder, [
            'type' => 'quick_signal',
            'channel' => $validated['channel'],
            'signal' => $validated['signal'],
        ]));

        return response()->json([
            'message' => 'Radio signal saved.',
            'data' => [
                'active_transmission' => $this->feedQuery->activeRadioTransmission($responder),
            ],
        ], 201);
    }

    private function insertRadioLog(object $responder, array $message): int
    {
        $communicationId = $this->support->nextId('responder_communication_logs', 'communication_id');
        $activeEvent = $this->support->activeEvent();
        $now = now();

        DB::table('responder_communication_logs')->insert($this->support->filterColumns('responder_communication_logs', [
            'communication_id' => $communicationId,
            'responder_id' => $responder->responder_id,
            'team_id' => $responder->team_id,
            'team_name' => $responder->team_name,
            'disaster_id' => $activeEvent->event_id ?? null,
            'message' => json_encode($message, JSON_UNESCAPED_SLASHES),
            'timestamp' => $now,
        ]));

        return $communicationId;
    }

    private function radioMessage(Request $request, object $responder, array $values): array
    {
        $activeEvent = $this->support->activeEvent();
        $channel = (string) ($values['channel'] ?? 'team');

        return array_merge([
            'channel' => $channel,
            'channel_label' => $this->radioPresenter->channelLabel($channel),
            'responder_id' => $responder->responder_id,
            'responder_name' => $responder->full_name ?: 'Responder',
            'responder_code' => $responder->responder_code,
            'team_id' => $responder->team_id,
            'team_name' => $responder->team_name ?: 'Unassigned team',
            'event_id' => $activeEvent->event_id ?? null,
            'event_name' => $activeEvent->name ?? 'No active event',
            'saved_by_user_id' => $request->user()?->user_id,
        ], $values);
    }







    private function missingTableResponse(string $table): JsonResponse
    {
        return response()->json([
            'message' => "The {$table} table is not available yet. Connect to the shared database or ask the DB member to apply the approved schema.",
        ], 503);
    }
}







