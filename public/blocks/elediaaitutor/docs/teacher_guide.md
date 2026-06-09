# Teacher / block configuration guide

Add the block with editing mode on: **Add a block ▸ eLeDia.ai Tutor**. Then open
the block's gear menu ▸ **Configure**.

## Per-instance settings

| Setting | Effect |
|---|---|
| **Block title** | The heading shown on the block. |
| **Display mode** | `Embedded`, `Docked floating panel`, `Modal dialog`, or `Full screen`. Overrides the site default for this instance. |
| **Pass course context** | When on (and the block is on a course page), the current course id is sent to the tutor so it can answer course-specific questions. |
| **Fixed course id** | Force a specific course id regardless of the page. Leave `0` to use the page context. Useful on a Dashboard block that should always reference one course. |
| **Welcome message** | The greeting shown when the chat opens. |
| **Assistant persona label** | The name shown in the header (e.g. "Biology Tutor"). |
| **Enable conversation history** | Show the history panel for this instance (also requires the user's `viewhistory` capability). |

## Tips

- One block per page is allowed; place it where learners naturally look for help.
- For a course-wide assistant, leave **Pass course context** on and the block on
  the course page — no fixed id needed.
- If your site disables course chat globally, course context is ignored even
  when configured here.
