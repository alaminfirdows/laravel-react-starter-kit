---
name: icp-definition
description: Defines or refines a startup's Ideal Customer Profile from interviews, research and project context. Use for ICP, target customer, customer segment or "who should we sell to" tasks.
---

# ICP Definition

Goal: one approved ICP document (`doc_type: icp`) the whole company can use. Keep it to ~1 page.

## Inputs (fetch, don't ask, when available)

- `get_project_context` → business model, market, stage, problem/solution.
- `search_knowledge` → interviews (pains, quotes, objections), competitor ICPs, existing customers.
- Ask the founder only for gaps: current customers/pilots, who pays vs who uses, deal size expectations.

## Method

1. List 2–4 candidate segments (firmographics + trigger event + pain).
2. Score each 1–5 on: pain intensity, ability to pay, reachability, fit with current product, evidence strength (count interview quotes).
3. Pick the primary ICP; note one secondary to revisit.
4. Write the document using `references/icp-template.md`.
5. Flag every claim that has no evidence as an assumption to validate, and propose 3 validation steps.

## Output

`save_knowledge(doc_type="icp", status="draft")`, then attach the document as evidence and complete the action per the task-runner protocol. Ask the founder to approve before setting it `approved`.
