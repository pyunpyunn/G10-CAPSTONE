# RESQPERATION UI Text and Operation Rules Note

This note keeps longer explanations out of the live screens. The web and mobile UI should use short labels, clear actions, and only the information needed for the current task.

## Dispatch UI Rules

- New dispatches require an active disaster event.
- HQ/Admin selects a GPS-tagged household target from the affected purok.
- The assigned area is automatically based on the selected purok.
- A responder or team can receive only one active dispatch during the same disaster event.
- Busy responders are shown for context but are not selectable.
- HQ-created dispatches start as `dispatched`, meaning assigned and waiting for rescuer acceptance.
- The rescuer mobile app moves the assignment to `accepted`, then `en_route`, then `on_scene`, then `completed`.
- When there is no active disaster event, dispatch views return to standby mode and old active-looking states should not block current operations.

## Radio / PTT Rules

- Rescuer radio uses tap-to-record voice clips instead of hold-to-talk.
- Tap once to start recording. Tap again to stop and upload.
- Voice clips are saved to the database as radio communication logs.
- Same-team rescuers can see/hear the latest team voice clip from the radio feed.
- Radio logs become part of the archive records for the disaster event.
- The current implementation is a practical capstone-safe approach, not a full live WebRTC call system.

## Notification Rules

- Notifications should be actionable.
- Clicking a notification should mark it read and route the user to the relevant page.
- Passive audit logs should not appear as notifications.
- Clicking outside the notification panel should close it.

## Weather and Map Text Rules

- Weather screens should show concise weather values and official PAGASA links.
- Broadcast decisions must still be confirmed through PAGASA official warnings.
- Map legends and filters should be short and visual.
- Household status colors only matter during an active disaster event.

## General UI Copy Rules

- Prefer short labels such as `Assigned`, `GPS`, `Unsafe`, `Pending`, `Route target`, and `Voice clip`.
- Avoid long instructional paragraphs inside operational screens.
- Put explanations in documentation unless the user needs the text to complete the task safely.
- Keep empty states short and action-oriented.
