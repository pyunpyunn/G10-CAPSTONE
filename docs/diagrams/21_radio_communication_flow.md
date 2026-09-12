# 21 - Radio Communication Flow

This diagram documents the rescuer mobile radio communication flow. The current beginner-friendly implementation is voice-clip based rather than continuous live WebRTC.

```mermaid
sequenceDiagram
  autonumber
  actor Sender as Rescuer sender
  participant SenderApp as Sender mobile app
  participant Permission as Microphone permission
  participant API as Laravel API
  participant Storage as Audio storage
  participant DB as Database
  participant ReceiverApp as Team member app
  actor Receiver as Rescuer receiver
  participant HQ as HQ/Admin archive

  Sender->>SenderApp: Taps telephone/radio button
  SenderApp->>Permission: Request microphone permission
  Permission-->>SenderApp: Permission granted
  SenderApp->>SenderApp: Start recording PTT clip
  Sender->>SenderApp: Taps again to stop
  SenderApp->>API: POST /api/v1/rescuer/radio with audio clip
  API->>Storage: Save audio file
  API->>DB: Save responder_communication_log
  DB-->>API: Saved with team, event, sender, duration, file path
  API-->>SenderApp: Upload success

  loop Poll or refresh team radio queue
    ReceiverApp->>API: GET /api/v1/rescuer/radio/team
    API->>DB: Read unplayed team radio logs
    API-->>ReceiverApp: Sender profile circles and pending PTT count
  end

  ReceiverApp-->>Receiver: Shows teammate circle with active/pending indicator
  Receiver->>ReceiverApp: Taps teammate circle
  ReceiverApp->>API: GET radio clip metadata/file URL
  API->>DB: Mark clip as opened or played for this receiver
  API-->>ReceiverApp: Audio URL
  ReceiverApp-->>Receiver: Plays first pending PTT clip
  ReceiverApp->>DB: Next refresh decreases pending count

  alt Disaster event is closed
    API->>DB: Radio logs stay linked to ended disaster event
    HQ->>API: GET archive radio logs
    API->>DB: Read responder_communication_logs by event
    API-->>HQ: Radio logs available in archive
  else New disaster event starts
    API->>DB: New event uses separate radio log scope
  end
```

## Radio behavior rules

- Only members of the same rescue team should receive team radio clips.
- The indicator should show who sent audio and how many unplayed clips exist.
- Logs should be paginated instead of loading all records at once.
- Radio logs should remain available in the event archive after disaster closure.
- If live push is added later, this flow can be upgraded to WebSocket/WebRTC while keeping database logs.

