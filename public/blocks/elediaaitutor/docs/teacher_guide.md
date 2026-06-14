# Teacher / block configuration guide

Add the block with editing mode on: **Add a block ▸ eLeDia.ai Tutor**. Then open
the block's gear menu ▸ **Configure**.

## Per-instance settings

| Setting | Effect |
|---|---|
| **Block title** | The heading shown on the block. |
| **Display mode** | `Docked floating panel` (the default — a launch button that opens a tidy chat window), `Embedded` in-page panel, `Modal dialog`, or `Full screen`. Overrides the site default for this instance. |
| **Pass course context** | When on (and the block is on a course page), the current course id is sent to the tutor so it can answer course-specific questions. |
| **Fixed course id** | Force a specific course id regardless of the page. Leave `0` to use the page context. Useful on a Dashboard block that should always reference one course. |
| **Welcome message** | The greeting shown when the chat opens. |
| **Prompt starters** | Suggested questions shown as one-click chips under the welcome (one per line). |
| **Persona** | The tutor's **name, role, tone, audience** and free-text **custom instructions** that shape how it speaks (e.g. "a patient first-year maths tutor; warm and encouraging"). |
| **Design** | Colours, surfaces, text, message bubbles, fonts, corner roundness, spacing, shadows, the launcher button and the footer — each edited with a real colour picker (click a swatch or paste a hex code) and plain-language dropdowns, never code. |
| **Logo & avatar** | Upload your own tutor logo and conversation avatar for this block. |
| **Enable conversation history** | Show the history panel for this instance (also requires the user's `viewhistory` capability). |

Which design and persona fields you see depends on what your administrator has
opened for per-course editing — anything they have locked keeps the site value
and is not shown here.

## Apply, import or export a tutor

The **Configure** form has a *Manage this tutor* link (available once the block
exists). From there you can, **scoped to this course's block**:

- **Apply** one of the institution's ready-made tutors (a preset or a saved
  profile) — its full look and persona in one click;
- **Import** a tutor file someone sent you (settings *and* images, as a single
  bundle); or
- **Export** this block's tutor as a file to share or reuse elsewhere.

Importing copies the look onto your block (a snapshot), so later changes to the
original never alter yours.

## Tips

- One block per page is allowed; place it where learners naturally look for help.
- For a course-wide assistant, leave **Pass course context** on and the block on
  the course page — no fixed id needed.
- If your site disables course chat globally, course context is ignored even
  when configured here.
