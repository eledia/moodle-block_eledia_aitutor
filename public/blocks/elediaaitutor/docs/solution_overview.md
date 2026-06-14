# eLeDia.ai Tutor — Solution Overview

> **An agentic AI tutor for Moodle that actually knows your courses.**
> A retrieval-augmented (RAG) assistant that lives inside Moodle *and* in any
> AI client your team already uses — grounded in your course content, able to act
> on live Moodle data, fully brandable to your institution, and powered by the
> language model of your choice.

**Document:** Solution Overview · **Version:** 2.0 · **Date:** 14 June 2026 ·
**Prepared by:** eLeDia GmbH, Berlin

---

> **Note for the document designer / downstream agent**
> This is the source content for a polished, customer-facing brochure. Lines
> marked `📸 SCREENSHOT` indicate where to place visuals; the full shot list is at
> the end. Keep the claims as written — they reflect what the product actually
> does. An English and a German edition are both maintained (see
> `solution_overview_de.md`); the products ship with English and German UIs.

---

## 1. What we offer

eLeDia combines several building blocks into one seamless learning assistant:

1. **eLeDia.ai Tutor** — a polished, native chat experience embedded directly in
   Moodle, fully brandable and configurable per course or site-wide.
2. **Moodle MCP Server** — a secure gateway that turns Moodle into a *tool
   provider* any AI agent can use, on behalf of the individual learner and
   strictly within their permissions.
3. **The agentic RAG / Tutor service** — the "brain": it retrieves knowledge from
   your course materials, reasons in multiple steps, calls Moodle when it needs
   live data, and answers in natural language — driven by the AI model you choose.
4. **Automatic content ingestion** — keeps the tutor's knowledge base current as
   your courses change (Section 7).

Together they deliver a tutor that is **grounded** (it cites real sources),
**connected** (it looks things up in Moodle in real time), **on-brand** (it looks
and speaks like *your* institution), and **flexible** (it runs on our models or
yours).

📸 SCREENSHOT: the eLeDia.ai Tutor chat open in a Moodle course — a formatted
answer with a source-citation card and the suggested-question chips.

---

## 2. The learner experience in Moodle

A beautiful, accessible chat assistant sits right inside the course. No new
logins, no context switching — learners ask questions in plain language and get
clear, formatted answers that link back to the right course resources.

- **Display modes to fit any page.** A **docked floating panel** (the default — a
  launch button that opens a tidy chat window), an **embedded** in-page panel, a
  centred **modal**, or **full-screen**. Choose per site and per course.
- **Mobile and accessible.** Responsive layouts, keyboard navigation,
  screen-reader labels and reduced-motion support throughout.
- **Suggested questions ("prompt starters").** Greet learners with one-click
  starter questions so they always know how to begin.
- **Answer styles.** Switch the teaching mode between **Explain** (full
  explanations), **Hints only** (guides without giving the solution away), and
  **Quiz me** (practice questions) — teachers can pin a style or let learners
  choose.
- **Conversation history.** Learners can revisit and resume their earlier
  conversations; history is kept separately per course (or combined in a global
  placement).
- **Privacy first.** Before the first use, learners read and acknowledge your
  privacy guidelines; they can review them any time and delete their own data.

📸 SCREENSHOT: the four display modes side by side (docked / embedded / modal /
full-screen).
📸 SCREENSHOT: the welcome screen with prompt-starter chips and the
Explain / Hints / Quiz answer-style selector.

---

## 3. Make it your own — branding, personas & "tutor profiles"

The eLeDia.ai Tutor is **fully brandable** — it can look and sound like a natural
part of your institution rather than a generic chatbot.

### Every visual detail is a setting
Colours, surfaces, text, message bubbles, fonts, corner roundness, spacing,
shadows, the avatar glow, the launcher button — **every design detail is editable
through friendly controls**: real colour pickers (click a swatch *or* paste a hex
code) and plain-language dropdowns ("Small / Medium / Large", "Subtle / Strong"),
never raw code. Each setting carries a short explanation and example.

📸 SCREENSHOT: the colour pickers and named dropdowns in the design settings.

### A custom persona & system prompt
Give the tutor a **name, role, tone and audience**, plus free-text **instructions**
that shape how it speaks — e.g. "a patient first-year maths tutor; warm and
encouraging." This persona is sent to the AI as guidance (it never overrides
safety or grounding rules).

📸 SCREENSHOT: the "Persona & system prompt" fields.

### Your logo and avatar
Upload your own **tutor logo** (header + launcher) and **conversation avatar**;
they appear throughout the chat.

### Ready-made looks — and a bit of fun
Ship with **five built-in presets** — *eLeDia (default)*, *Forest*, *Midnight*,
*High contrast*, and the playful *HAL 9000* — each a complete, legible palette
plus a starter persona. Apply one with a single click as your site default or to
any individual block. Every preset opens as the docked floating panel.

📸 SCREENSHOT: the "Tutors" library page showing the preset cards, each with a
live mini-preview of its palette.

### Save, share and reuse with "Tutor profiles"
Create your own named **tutor profiles** and **export / import** them as a single
file — settings *and* images included. Build a look once and reuse it across
courses or even other Moodle sites. Importing a profile snapshots it onto the
target (no hidden links to break later).

📸 SCREENSHOT: exporting a tutor and the import dialog.

### White-label
Replace or hide the footer credit for a fully white-labelled assistant, and add
institution-specific custom CSS where you want pixel-level control.

---

## 4. Who controls what — admin governance & teacher self-service

The tutor balances **institutional consistency** with **course-level freedom**.

- **Admins set the site-wide default tutor** and, for *every* setting, decide
  whether teachers may override it per course. By default all visual/persona
  settings are open to teachers; an admin can lock any of them down to keep a
  consistent look.
- **Teachers tailor their course tutor** using only the settings the admin has
  opened up — with the same friendly colour pickers and dropdowns.
- **Teacher self-service import/export.** From the block's configuration, an
  editing teacher can **export** their tutor, **import** one they were sent, or
  **apply** one of the institution's ready-made tutors — scoped to their own
  course.
- **Central management.** Admins manage the whole tutor library (presets and saved
  profiles), apply a tutor to the site or to any specific block, and import/export
  site-wide tutors — all from one page.

📸 SCREENSHOT: the admin "Manage tutors" page — site tutors and the per-instance
apply / export / import controls.
📸 SCREENSHOT: the per-setting "Allow per-instance override" checkboxes in the
admin settings.

---

## 5. Two ways to use the same tutor

The same assistant is available wherever your users work.

### In Moodle — for every learner and teacher
The native chat block (Sections 2–3), embedded directly in the course.

### In any MCP host — for power users, staff and integrations
Because Moodle is exposed as a **Model Context Protocol (MCP)** server, the very
same Moodle capabilities can be used from external AI clients — for example
**Claude Desktop**, AI-enabled IDEs, or a **custom MCP host** you build. A staff
member connects their preferred AI app and works with Moodle data directly, using
a personal, permission-scoped token.

📸 SCREENSHOT: Claude Desktop (or a custom client) connected to the Moodle MCP
server, answering a question about a course.

---

## 6. Agentic and grounded — not just a chatbot

- **Retrieval-augmented (RAG):** answers are based on *your* course content, not
  just the model's training data — reducing hallucination and keeping responses
  on-curriculum. Sources are shown alongside the answer.
- **Agentic:** the tutor reasons in steps and uses **tools** — it can look up the
  learner's enrolled courses, upcoming assignments and deadlines, grades, course
  contents and announcements, search content, and more — live, from Moodle.
- **Permission-aware by design:** every Moodle lookup runs under the learner's own
  identity and capabilities. The tutor can never reveal data the user couldn't
  already access themselves.
- **General-knowledge mode.** Where a course has no knowledge base yet, the tutor
  can still help from the model's general knowledge (clearly indicated) — or be
  restricted to grounded answers only.

📸 SCREENSHOT: an answer that used a live Moodle lookup ("What's due this week?")
with the cited source card and the "based on course materials" badge.

**Example questions it handles well**

- "Which courses am I enrolled in, and what should I focus on this week?"
- "What do I need to do for this week's assignment, and when is it due?"
- "Explain this week's topic and point me to the right resource in the course."
- "How am I doing so far — what are my current grades?"

---

## 7. Always current — automatic content ingestion

A tutor is only as good as its knowledge. eLeDia's ingestion continuously feeds
your course content into the RAG knowledge base so answers stay grounded and
up to date. It extracts the teaching text from your activities and resources and
hands it to the RAG service — automatically on every change, and on demand via a
bulk re-index.

- **Broad coverage:** Moodle's core activities and resources (Book, Page, Lesson,
  Quiz, Glossary, Assignment, File, Folder, SCORM, IMS packages, H5P and more)
  plus popular add-ons.
- **Automatic and incremental:** new, changed or deleted content syncs straight
  away; a single re-index refreshes an entire course.
- **Privacy-friendly:** only *teaching content* is ingested — never learner
  submissions or answers.
- **Multi-tenant clean:** deterministic source identifiers keep the knowledge base
  tidy across many Moodle instances.
- **Extensible:** new activity types can be supported without changing core code.

---

## 8. Insight for teachers — question analytics

An opt-in, **privacy-safe** analytics report shows teachers what learners are
asking — **without any learner identities**. It turns everyday questions into a
picture of where a cohort needs help.

- **At-a-glance stats:** total questions, questions in the last 7 days, and how
  many answers were grounded in course materials.
- **Confusion hotspots:** questions clustered by topic / primary source, so you
  can see which materials trigger the most questions — linked to the activity.
- **Trend over time** and a **recent-questions** list (question text only, no
  names), with the answer style used.
- Data is retained only as long as you configure and is covered by the privacy
  tools.

📸 SCREENSHOT: the redesigned analytics report — stat cards, the "hotspots" bars
and the questions-per-day chart.

---

## 9. Your model, your data — your choice

The RAG service connects to a **LiteLLM gateway**, giving you full control over
*which* large language model powers the tutor:

- **Use eLeDia-hosted models** — we operate the gateway and provide access to
  leading models (for example OpenAI), with European hosting options.
- **Bring your own provider** — point the gateway at your own foundation-model
  subscription or a self-hosted/open model. Switch or mix providers without
  changing anything in Moodle.

This means **no vendor lock-in at the model layer**, predictable cost control, and
the ability to meet data-residency and procurement requirements.

📸 SCREENSHOT / DIAGRAM: the LiteLLM gateway routing to multiple model providers.

---

## 10. Security, privacy & data sovereignty

Built to Moodle and enterprise security standards from day one:

- **Secrets stay server-side.** Moodle tokens and model/provider credentials are
  never exposed to the browser; the learner's app only ever talks to Moodle.
- **User-scoped, short-lived tokens.** Each AI interaction uses a token bound to
  the individual learner and the configured service — automatically provisioned,
  rotated, revoked and auditable.
- **Least privilege.** The tutor sees exactly what the learner sees — nothing more.
- **Documented consent.** First-use acknowledgement of your privacy guidelines,
  with self-service data deletion.
- **GDPR-ready.** Full Moodle Privacy API support; conversation data and its
  retention are transparent, exportable and erasable.
- **You stay in control of the data flow.** Choose where the RAG service and the
  models run; on-prem / EU hosting options are available.

📸 SCREENSHOT: the privacy guidelines dialog and/or the admin token overview.

---

## 11. How it works

```text
          ┌─────────────────────────┐        ┌──────────────────────────┐
          │   Moodle (your LMS)      │        │  Any MCP host            │
          │  ┌───────────────────┐   │        │  (Claude Desktop, custom │
          │  │ eLeDia.ai Tutor    │  │        │   app, IDE, …)           │
          │  │ chat block         │  │        └────────────┬─────────────┘
          │  └─────────┬─────────┘   │                     │
          │            │ server-side │                     │ user-scoped
          │            ▼ (secrets    │                     │ MCP token
          │   user-scoped MCP token  │                     │
          └────────────┼─────────────┘                     │
                       │                                    │
                       ▼                                    │
        ┌──────────────────────────────┐                   │
        │  Agentic RAG / Tutor service  │◄──────────────────┘
        │  • retrieval over course data │
        │  • multi-step reasoning        │
        │  • calls Moodle tools          │──────┐
        └───────────────┬───────────────┘       │ calls back into Moodle
                        │                        ▼  (as the learner, scoped)
                        │              ┌────────────────────────────┐
                        ▼              │   Moodle MCP Server         │
        ┌──────────────────────────┐  │   my_courses, assignments,  │
        │  LiteLLM model gateway    │  │   grades, contents, calendar│
        │  routes to the chosen LLM │  └────────────────────────────┘
        └───────────────┬──────────┘
                        │
            ┌───────────┴───────────┐
            ▼                       ▼
   eLeDia-hosted models     Customer's own provider
   (e.g. OpenAI via our      (bring your own foundation
    gateway, EU options)      model / endpoint)
```

📸 SCREENSHOT / DIAGRAM: replace the ASCII diagram above with a designed graphic.

**Step by step, when a learner asks a question:**

1. They type into the eLeDia.ai Tutor block in Moodle (or their MCP client).
2. Moodle hands the request to the **agentic RAG service** with a **short-lived,
   user-scoped token** — never exposed to the browser — and the tutor's persona.
3. The RAG service **retrieves** relevant passages from your course knowledge base
   and, when it needs live information (courses, deadlines, grades, a resource),
   **calls back into Moodle** through the MCP server — acting as that learner and
   only seeing what they're allowed to see.
4. It composes the answer using the **language model you selected** (via the
   LiteLLM gateway) and returns a grounded, cited response.

---

## 12. What you get

| Component | Role |
|---|---|
| **eLeDia.ai Tutor** (Moodle block) | The in-Moodle chat experience, full branding & tutor profiles, and the secure connector. |
| **Moodle MCP Server** (web service) | Exposes Moodle as permission-scoped tools for any AI agent; self-service token management for users. |
| **Agentic RAG / Tutor service** | Retrieval over your content + multi-step reasoning + Moodle tool use. |
| **Content ingestion** | Extracts course content and feeds the RAG knowledge base — automatically and on demand. |
| **LiteLLM model gateway** | Routes to eLeDia-hosted models or your own provider. |
| **Integration & onboarding** | Setup, knowledge-base ingestion, model configuration, branding and training. |

---

## 13. Why eLeDia

- A **complete, integrated** solution — not a bolt-on chatbot: the LMS, the agent,
  the tools and the model layer are designed to work together.
- **On-brand:** fully customisable look and persona, with shareable tutor profiles.
- **Open standards (MCP):** the same investment serves Moodle *and* external AI
  tooling.
- **Model-agnostic** and **data-sovereign** — you choose the AI and where it runs.
- **Native, accessible, bilingual** (English & German) Moodle plugins, CI-tested
  against current Moodle releases.
- Delivered and supported by **eLeDia**, a specialist Moodle partner.

📸 SCREENSHOT: eLeDia logo / branding lockup for the closing page.

---

## 14. In plain words (glossary box)

- **MCP (Model Context Protocol):** an open standard that lets AI assistants use
  external tools and data sources securely. We use it so any AI client can work
  with Moodle, and so the tutor can use Moodle as a toolset.
- **RAG (Retrieval-Augmented Generation):** grounding AI answers in your own
  content instead of relying solely on the model's memory.
- **Agentic:** the AI takes multiple reasoning steps and calls tools (look things
  up, act) rather than producing a single one-shot reply.
- **Tutor profile / preset:** a complete, named look-and-persona for the tutor that
  can be applied, saved, exported and imported.
- **LiteLLM:** a gateway that gives a single, consistent way to connect to many
  language-model providers — so the model can be swapped or chosen freely.

---

## Screenshot shot list (for the designer)

Capture on a clean demo course, ideally showing two contrasting presets so the
branding range is obvious. Suggested order:

1. **Hero:** tutor (docked panel) open in a course, formatted answer + source card.
2. **Display modes:** docked, embedded, modal, full-screen (a 2×2 montage).
3. **Welcome state:** persona greeting, prompt-starter chips, answer-style selector.
4. **Design settings:** colour pickers + named dropdowns (block config or Tutors editor).
5. **Persona fields:** name / role / tone / audience / instructions.
6. **Tutors library:** preset cards with palette previews (show HAL + Forest).
7. **Import / export:** the export download and the import dialog.
8. **Admin governance:** "Allow per-instance override" checkboxes.
9. **Manage tutors:** site tutors + per-instance apply/export/import.
10. **MCP host:** Claude Desktop (or custom client) answering from Moodle.
11. **Grounded answer:** live Moodle lookup with "based on course materials" badge.
12. **Analytics report:** stat cards, hotspots bars, per-day chart.
13. **Privacy:** first-use consent / privacy guidelines dialog.
14. **Architecture diagram:** designed version of the Section 11 graphic.
15. **Closing:** eLeDia branding lockup.

---

*Prepared for sales enablement. Technical references — the plugin README, the
admin / integration guides, and the RAG / Tutor MCP server specification —
accompany this document for prospects who want the engineering detail.*
