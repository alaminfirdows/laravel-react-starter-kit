# P2 Knowledge — Outline

> **For agentic workers:** OUTLINE. Before execution, re-run superpowers:writing-plans on this file to expand each task into full TDD steps against the code that exists after P1.

**Goal:** Each project has a searchable brain: knowledge documents (ICP, positioning, interviews, …) with versions, chunked + embedded for hybrid search, a cached context snapshot for prompts, and a decision log.

**Architecture:** `knowledge_documents` + `knowledge_document_versions` (Markdown). Save → `ChunkDocument` → queued `EmbedDocumentJob` (`laravel/ai` embeddings) → `knowledge_chunks` with `vector(1536)` + generated `tsvector`. `HybridSearch` merges vector and full-text ranks with reciprocal rank fusion. `BuildContextSnapshot` writes `projects.context_snapshot_md`.

**Spec:** `docs/DATA_MODEL.md` §D, rule 10 in `docs/CLAUDE.md`, `docs/PROJECT_CONTEXT.md` (knowledge section).

## Global Constraints

- pgvector extension required (`CREATE EXTENSION vector` in migration; CI image `pgvector/pgvector:pg16`).
- `AI_EMBEDDING_DIMENSIONS=1536`; re-embed only on new version (checksum change).
- Singleton doc types (icp, positioning, messaging, brand, brief): one `approved` doc per project, enforced in `SaveDocument`.
- Tests use `Embeddings::fake()`; no network.

## Review Focus

1. Search in project A never returns chunks of project B (scoping in `HybridSearch`).
2. Approving a second `icp` archives the previous approved one, not both approved.
3. Saving the same body twice → no new version, no re-embed.
4. Very long document (> 200 KB) → chunked, no single chunk over token limit.
5. Embedding provider failure → job retries, document stays searchable by full text.

## Tasks

| #   | Task                           | Files / classes                                                                                                                                                                                             | Tests                                                             |
| --- | ------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------- |
| 1   | pgvector + schema              | migration enabling `vector`; `knowledge_documents`, `knowledge_document_versions`, `knowledge_chunks` (HNSW cosine index, GIN on `tsv`); enums `DocType`, `DocStatus`, `DocSource`                          | `KnowledgeSchemaTest`                                             |
| 2   | Models                         | `Knowledge\Models\{KnowledgeDocument,KnowledgeDocumentVersion,KnowledgeChunk}` (`AsVector` cast) + factories                                                                                                | `KnowledgeModelTest`                                              |
| 3   | Save + version                 | `Knowledge\Actions\SaveDocument` (checksum, version bump, singleton rule, activity `knowledge.saved`)                                                                                                       | `SaveDocumentTest` (Review Focus 2, 3)                            |
| 4   | Chunk                          | `Knowledge\Actions\ChunkDocument` (split by headings, `heading_path`, ~500 tokens, overlap 50)                                                                                                              | `ChunkDocumentTest` (Review Focus 4)                              |
| 5   | Embed                          | `Knowledge\Jobs\EmbedDocumentJob` (batch embeddings, `embedded_at`, retries/backoff)                                                                                                                        | `EmbedDocumentJobTest` with `Embeddings::fake()` (Review Focus 5) |
| 6   | Hybrid search                  | `Knowledge\Queries\HybridSearch(project, query, limit, docTypes?)` — `whereVectorSimilarTo` + `ts_rank`, RRF k=60                                                                                           | `HybridSearchTest` (ranking, scoping — Review Focus 1)            |
| 7   | Context snapshot               | `Project\Actions\BuildContextSnapshot` (profile + approved singleton docs summaries + open decisions, ≤ 6 000 chars); rebuilt on project/doc change (queued)                                                | `BuildContextSnapshotTest`                                        |
| 8   | Decisions                      | migration `decisions`; `Knowledge\Actions\RecordDecision`; model + factory                                                                                                                                  | `RecordDecisionTest`                                              |
| 9   | MCP tools                      | `SearchKnowledge`, `GetDocument`, `SaveDocument`, `RecordDecision` tools; `GetProjectContext` uses snapshot                                                                                                 | `McpKnowledgeToolsTest`                                           |
| 10  | Prompts use knowledge          | `RenderFullPrompt` gains `{{ knowledge.<doc_type> }}` placeholders (approved doc body, trimmed to budget)                                                                                                   | `RenderFullPromptTest` additions                                  |
| 11  | UI                             | pages `projects/knowledge/index` (list by type, search box), `projects/knowledge/show` (Markdown + versions), `projects/knowledge/edit` (Tiptap `MarkdownEditor`), decisions list; sidebar link "Knowledge" | `KnowledgeHttpTest`                                               |
| 12  | Structured research (optional) | `interviews`, `competitors` tables + forms, mirrored to docs                                                                                                                                                | `InterviewTest`                                                   |
| 13  | Docs + gate                    | update DATA_MODEL §D; `composer ci:check`                                                                                                                                                                   | —                                                                 |
