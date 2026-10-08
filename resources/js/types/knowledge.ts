export type DocStatus = 'draft' | 'approved' | 'archived';
export type DocSource = 'user' | 'claude_mcp' | 'app_ai' | 'upload' | 'import';

export type KnowledgeDocumentVersion = {
    id: string;
    version: number;
    changeNote: string | null;
    createdByType: 'user' | 'agent' | 'system';
    createdAt: string | null;
};

export type KnowledgeDocument = {
    id: string;
    docType: string;
    docTypeLabel: string;
    title: string;
    status: DocStatus;
    source: DocSource;
    version: number;
    isEmbedded: boolean;
    updatedAt: string | null;
    bodyMd?: string;
    versions?: KnowledgeDocumentVersion[];
};

export type KnowledgeSearchResult = {
    chunkId: string;
    documentId: string;
    documentTitle: string;
    docTypeLabel: string;
    headingPath: string | null;
    snippet: string;
};

export type Decision = {
    id: string;
    title: string;
    decisionMd: string;
    rationaleMd: string | null;
    alternatives: string[];
    decidedOn: string | null;
    revisitOn: string | null;
    source: DocSource;
    owner?: string | null;
};
