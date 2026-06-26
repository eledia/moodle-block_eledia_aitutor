# eLeDia.ai Tutor

The eLeDia.ai Tutor provides an AI-supported chat directly in Moodle. Depending on the site configuration, it appears as a course block, docked panel, modal dialog, or full-screen view. The tutor always acts with the current user's Moodle permissions and can only access content that user is allowed to see.

## Learner Use

On first use, learners confirm the privacy guidelines. They can then ask questions about the current course or general Moodle questions. Prompt starters appear below the welcome message when configured by the site or block.

- Press **Enter** to send; use **Shift+Enter** for a new line.
- Switch the answer style between **Explain**, **Hints only**, and **Quiz me** when allowed by the site.
- Copy answers or retry failed responses.
- Start a fresh thread with **New conversation**.
- Open saved conversations with the clock icon when history is enabled.
- Delete personal tutor data from the privacy dialog.

## Course Context

In a course, the tutor can answer course-specific questions about activities, materials, or next steps. Outside a course, it can help with general Moodle navigation and visible courses. Course chat only works where the tutor block is enabled for that course.

## Managing Tutor Designs

Administrators manage tutor designs under **Site administration > Plugins > Blocks > eLeDia.ai Tutor > Tutors**. The page contains built-in presets and saved tutor profiles.

- Apply a preset or saved tutor to the whole site.
- Create, edit, and duplicate custom tutor profiles.
- Import and export tutor packages including settings and images.
- Assign an individual tutor design to specific block instances.
- Teachers can use block-specific designs when the site governance allows it.

## Administration

The AI connection is configured in the block settings. Administrators maintain the RAG/MCP endpoint, authentication, tool names, streaming, limits, privacy text, and Moodle MCP external service selection. Without a selected MCP service, the tutor cannot execute Moodle-aware AI functions.

For local development, insecure transport and private hosts can be allowed. In production, keep those options disabled unless the RAG/MCP service is intentionally operated on an internal network.

## Privacy and Accessibility

The tutor enforces privacy acknowledgement server-side. The interface is keyboard-operable, announces new answers to screen readers, and respects reduced-motion preferences.
