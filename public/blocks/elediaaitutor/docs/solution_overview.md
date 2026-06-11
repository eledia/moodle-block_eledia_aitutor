# eLeDia.ai Tutor — Solution Overview

> **An agentic AI tutor for Moodle that actually knows your courses.**
> A retrieval-augmented (RAG) assistant that lives inside Moodle *and* in any
> AI client your team already uses — grounded in your course content, able to act
> on live Moodle data, and powered by the language model of your choice.

---

> **Note for the document designer / downstream agent**
> This is the source content for a polished, customer-facing brochure. Sections
> marked `📸 SCREENSHOT` indicate where to place visuals. Keep the claims as
> written — they reflect what the product actually does. An English and a German
> edition are both in scope (the products ship with English and German UIs).

---

## 1. What we offer

eLeDia combines three building blocks into one seamless learning assistant:

1. **eLeDia.ai Tutor** — a polished, native chat experience embedded directly in
   Moodle (the `block_elediaaitutor` block).
2. **Moodle MCP Server** — a secure gateway (`webservice_elediamcp`) that turns
   Moodle into a *tool provider* any AI agent can use, on behalf of the
   individual learner and strictly within their permissions.
3. **The agentic RAG/Tutor service** — the "brain": it retrieves knowledge from
   your course materials, reasons in multiple steps, calls Moodle when it needs
   live data, and answers in natural language — driven by the AI model you choose.

Together they deliver a tutor that is **grounded** (it cites real sources),
**connected** (it can look things up in Moodle in real time), and **flexible**
(it runs on our models or yours).

📸 SCREENSHOT: the eLeDia.ai Tutor chat open in a Moodle course, showing a
formatted answer with source citations.

---

## 2. Two ways to use the same tutor

The same assistant is available wherever your users work.

### In Moodle — for every learner and teacher
A beautiful, accessible chat block sits right inside the course. No new logins,
no context switching. It adapts to the page (embedded panel, docked widget,
modal, or full-screen) and to the device (desktop, tablet, mobile). Learners ask
questions in plain language; the tutor answers with formatted text and links back
to the relevant course resources.

📸 SCREENSHOT: the four display modes side by side (embedded / docked / modal /
full-screen).

### In any MCP host — for power users, staff and integrations
Because Moodle is exposed as a **Model Context Protocol (MCP)** server, the very
same Moodle capabilities can be used from external AI clients — for example
**Claude Desktop**, AI-enabled IDEs, or a **custom MCP host** you build. A staff
member can connect their preferred AI app and have it work with Moodle data
directly, using a personal, permission-scoped token.

📸 SCREENSHOT: Claude Desktop (or a custom client) connected to the Moodle MCP
server, answering a question about a course.

---

## 3. How it works

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
        ┌──────────────────────────┐  │   (webservice_elediamcp)    │
        │  LiteLLM model gateway    │  │  my_courses, assignments,   │
        │  routes to the chosen LLM │  │  grades, contents, calendar │
        └───────────────┬──────────┘  └────────────────────────────┘
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
2. Moodle hands the request to the **agentic RAG service** together with a
   **short-lived, user-scoped token** — never exposed to the browser.
3. The RAG service **retrieves** relevant passages from your course knowledge base
   and, when it needs live information (the learner's courses, deadlines, grades,
   a specific resource), **calls back into Moodle** through the MCP server — acting
   as that learner and only seeing what they're allowed to see.
4. It composes the answer using the **language model you selected** (via the
   LiteLLM gateway) and returns a grounded, cited response.

---

## 4. Agentic and grounded — not just a chatbot

- **Retrieval-augmented (RAG):** answers are based on *your* course content, not
  just the model's training data — reducing hallucination and keeping responses
  on-curriculum. Sources are shown alongside the answer.
- **Agentic:** the tutor reasons in steps and uses **tools**. It can look up the
  learner's enrolled courses, upcoming assignments and deadlines, grades, course
  contents and announcements, and even draft messages — live, from Moodle.
- **Permission-aware by design:** every Moodle lookup runs under the learner's own
  identity and capabilities. The tutor can never reveal data the user couldn't
  already access themselves.

📸 SCREENSHOT: an answer that used a live Moodle lookup (e.g. "What's due this
week?") with the cited source card.

**Example questions it handles well**

- "Which courses am I enrolled in, and what should I focus on this week?"
- "What do I need to do for this week's assignment, and when is it due?"
- "Explain this week's topic and point me to the right resource in the course."
- "How am I doing so far — what are my current grades?"

---

## 5. Your model, your data — your choice

The RAG service connects to a **LiteLLM gateway**, which gives you full control
over *which* large language model powers the tutor:

- **Use eLeDia-hosted models** — we operate the gateway and provide access to
  leading models (for example OpenAI), with European hosting options.
- **Bring your own provider** — point the gateway at your own foundation-model
  subscription or a self-hosted/open model. Switch or mix providers without
  changing anything in Moodle.

This means **no vendor lock-in at the model layer**, predictable cost control, and
the ability to meet data-residency and procurement requirements.

📸 SCREENSHOT / DIAGRAM: the LiteLLM gateway routing to multiple model providers.

---

## 6. Security, privacy & data sovereignty

Built to Moodle and enterprise security standards from day one:

- **Secrets stay server-side.** Moodle tokens and model/provider credentials are
  never exposed to the browser; the learner's app only ever talks to Moodle.
- **User-scoped, short-lived tokens.** Each AI interaction uses a token bound to
  the individual learner and the configured service, automatically provisioned,
  rotated and revoked — fully auditable.
- **Least privilege.** The tutor sees exactly what the learner sees — nothing more.
- **GDPR-ready.** Full Moodle Privacy API support; conversation data and its
  retention are transparent, exportable and erasable. Where full transcripts live
  on the RAG service, that is clearly declared.
- **You stay in control of the data flow.** Choose where the RAG service and the
  models run; on-prem / EU hosting options are available.
- **Auditable & observable.** Security-relevant events are logged (without secrets
  or full message content) and surfaced in Moodle's standard reports.

📸 SCREENSHOT: the admin token/audit overview and/or the privacy settings.

---

## 7. Built for Moodle, ready for the enterprise

- **Native Moodle plugins**, following Moodle coding, accessibility and privacy
  standards — installed like any other plugin.
- **English and German** user interfaces out of the box.
- **Responsive & accessible** chat (keyboard navigation, screen-reader support,
  reduced-motion friendly).
- **Per-course or global** assistants — conversation history is kept separately
  per course, or combined in a global placement.
- **Configurable** display modes, personas, welcome messages, limits and tools —
  per site and per block.
- **CI-tested** and maintained against current Moodle releases.

---

## 8. What you get

| Component | Role |
|---|---|
| **eLeDia.ai Tutor** (Moodle block) | The in-Moodle chat experience and secure connector. |
| **Moodle MCP Server** (web service) | Exposes Moodle as permission-scoped tools for any AI agent; self-service token management for users. |
| **Agentic RAG/Tutor service** | Retrieval over your content + multi-step reasoning + Moodle tool use. |
| **LiteLLM model gateway** | Routes to eLeDia-hosted models or your own provider. |
| **Integration & onboarding** | Setup, knowledge-base ingestion, model configuration, training. |

---

## 9. Why eLeDia

- A **complete, integrated** solution — not a bolt-on chatbot: the LMS, the agent,
  the tools and the model layer are designed to work together.
- **Open standards** (MCP) mean the same investment serves Moodle *and* external
  AI tooling.
- **Model-agnostic** and **data-sovereign** — you choose the AI and where it runs.
- Delivered and supported by **eLeDia**, a specialist Moodle partner.

📸 SCREENSHOT: eLeDia logo / branding lockup for the closing page.

---

## 10. Glossary (for the brochure's "in plain words" box)

- **MCP (Model Context Protocol):** an open standard that lets AI assistants use
  external tools and data sources securely. We use it so any AI client can work
  with Moodle, and so the tutor can use Moodle as a toolset.
- **RAG (Retrieval-Augmented Generation):** the technique of grounding AI answers
  in your own documents/content instead of relying solely on the model's memory.
- **Agentic:** the AI can take multiple reasoning steps and call tools (look things
  up, act) rather than producing a single one-shot reply.
- **LiteLLM:** a gateway that gives a single, consistent way to connect to many
  different language-model providers — so the model can be swapped or chosen
  freely.

---

*Prepared for sales enablement. Technical references: the plugin README, the
admin/integration guides, and the RAG/Tutor MCP server specification accompany
this document for prospects who want the engineering detail.*
