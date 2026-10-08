# Catalog YAML

Import: `php artisan catalog:import [path]`. Upserts by `key`; `version` bumps when content changes. Children inherit `category` from the parent; max depth 3 levels.

```yaml
# categories.yaml
categories:
    - key: idea-validation
      name: Idea validation
      phase: research # CatalogPhase
      icon: lightbulb # lucide icon name
      sort_order: 1
      description_md: Prove people have the problem.

# prompts.yaml
prompts:
    - key: generic-task
      title: Generic task prompt
      target: chat # chat|cowork|code
      full_md: |
          You are helping {{ project.name }} — {{ project.one_liner }}.
          Task: {{ task.title }}
          {{ task.body_md }}
          Step: {{ action.title }}
          {{ action.instructions_md }}

# tasks/planning.yaml
tasks:
    - key: planning.problem-interviews
      category: idea-validation
      title: Run 10 problem interviews
      summary: Talk to people who have the problem before building.
      priority: p1
      est_minutes: 600
      body_md: |
          ## Why
          ...
      depends_on: [] # [{key: other.task, kind: hard}]
      actions:
          - key: write-script
            title: Draft an interview script
            type: ai
            executor: claude_desktop
            prompt: generic-task
            instructions_md: Draft 8 open questions about the problem.
      children:
          - key: planning.problem-interviews.recruit
            title: Recruit 10 interviewees
            actions:
                [
                    {
                        key: recruit,
                        title: Book 10 calls,
                        type: manual,
                        executor: user,
                    },
                ]

# packs.yaml
packs:
    - key: planning-starter
      name: Planning starter
      phase: planning # ProjectPhase → audience.phase
      is_default: true
      items:
          - task: planning.problem-interviews # include_subtree defaults to true
```
