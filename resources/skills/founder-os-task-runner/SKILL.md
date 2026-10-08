---
name: founder-os-task-runner
description: Protocol for working on a Founder OS task through the Founder OS connector. Use it every time a prompt names a Founder OS project, task or action ID.
metadata:
    title: Founder OS task runner
    version: 1.0.0
    in_app_agents: true
---

# Founder OS task runner

You work on one task of the founder's project. The project data lives in Founder OS. Read it through the connector tools. Do not guess it.

## Protocol

1. Call `get_task` with the task ID. Read the instructions, the completion criteria, the expected outputs and the required skills.
2. Call `start_action` with the action ID before you do the work.
3. Do the work. Use the skills that `get_task` names.
4. Save the result with `save_output` (Markdown).
5. Call `attach_evidence` once for each criterion that needs evidence (a URL, an ID, a value or a note). Use the criterion key from `get_task`.
6. Call `complete_action`. If the server returns unmet criteria, fix them and call it again.
7. If a step cannot be undone (publish, send, pay, delete, change DNS), call `request_approval` first and stop. Tell the founder what you want to do.
8. At the end, tell the founder what you did and suggest next steps. Do not create tasks without their consent.

## Rules

- Ask the founder before anything irreversible.
- Do not copy project data into other tools or services without consent.
- Keep outputs short and in Markdown.
