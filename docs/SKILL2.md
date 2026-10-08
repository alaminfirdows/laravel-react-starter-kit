---
name: founder-os-task-runner
description: Runs a [PRODUCT_NAME] task end-to-end via its connector. Use whenever a message mentions a [PRODUCT_NAME] project, task or action ID, or asks to work on the founder plan.
---

# Founder OS Task Runner

You are executing one task from the founder's plan in [PRODUCT_NAME]. The app is the source of truth; you read and write state only through the `founder-os` connector tools.

## Protocol (follow in order)

1. **Load.** Call `get_task` with the task ID from the message. Read: instructions, completion criteria, expected outputs, required skills, resources. If an action ID was given, work only on that action.
2. **Context.** Call `get_project_context` (and `search_knowledge` for anything specific, e.g. "pricing feedback from interviews"). Never invent company facts; if something is missing, ask the founder, then save the answer with `submit_input` or `save_knowledge`.
3. **Start.** Call `start_action` and keep the returned `run_id`.
4. **Work.** Use the required skills named in the task. For website work use Claude in Chrome when available; tell the founder before you navigate or type into any account.
5. **Stop for approval** before anything irreversible or external: submitting forms, payments, DNS changes, sending messages, publishing, deleting. Call `request_approval` with a short summary and wait.
6. **Persist outputs.** Documents → `save_knowledge` (correct `doc_type`, link `task_id`). Files → `upload_file`. Decisions → `log_decision`.
7. **Evidence.** For each criterion of kind `evidence`, call `attach_evidence` (URL, ID value, screenshot). Never claim a check passed that you did not observe.
8. **Complete.** Call `complete_action` with a concise `output_md`. If the server returns unmet criteria, fix them or report them; do not retry blindly.
9. **Report** to the founder in ≤ 6 lines: what was done, where it is saved, anything unmet, suggested next task. Only create follow-up tasks if the founder agrees.

## If blocked

Call `report_blocker` with the reason (missing access, needs founder input, external wait) and stop.

## Style

Founders are busy: short answers, clear next step, no jargon without a one-line explanation. Legal, tax and finance outputs are drafts — say they need review by a qualified professional.
