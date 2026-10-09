import type { Option, ProjectPhase } from './project';

export type CatalogStatus = 'draft' | 'published' | 'archived';

export type CatalogTreeNode = {
    key: string;
    title: string;
    status: CatalogStatus;
    version: number;
    isEdited: boolean;
    children: CatalogTreeNode[];
};

export type CatalogTreeCategory = {
    id: number;
    name: string;
    phase: string;
    tasks: CatalogTreeNode[];
};

export type CatalogTaskRef = { key: string; title: string };

export type AdminCatalogTask = {
    key: string;
    title: string;
    summary: string | null;
    bodyMd: string | null;
    categoryId: number;
    priority: string;
    estMinutes: number | null;
    difficulty: number | null;
    isOptional: boolean;
    status: CatalogStatus;
    version: number;
    publishedAt: string | null;
    adminEditedAt: string | null;
};

export type AdminCatalogAction = {
    key: string;
    title: string;
    type: string;
    executor: string;
    instructionsMd: string | null;
    prompt: string | null;
    config: string | null;
    isRequired: boolean;
    requiresApproval: boolean;
    version: number;
};

export type CatalogActionFormOptions = {
    actionTypeOptions: Option[];
    executorOptions: Option[];
    promptOptions?: Option[];
};

export type AdminPromptTemplate = {
    key: string;
    title: string;
    target: string;
    launcherMd: string | null;
    fullMd: string;
    skillKeys: string;
    version: number;
    adminEditedAt: string | null;
};

export type AdminPack = {
    key: string;
    name: string;
    descriptionMd: string | null;
    phase: ProjectPhase | null;
    isDefault: boolean;
    status: CatalogStatus;
    version: number;
    adminEditedAt: string | null;
    items?: string[];
    itemsCount?: number;
};

export type PackRootTask = {
    key: string;
    title: string;
    category: string;
    status: CatalogStatus;
};

export type PackVisibility = 'private' | 'public';

export type PackReviewStatus = 'pending' | 'approved' | 'rejected';

export type CommunityPack = {
    key: string;
    name: string;
    descriptionMd: string | null;
    phase: ProjectPhase | null;
    visibility: PackVisibility;
    status: CatalogStatus;
    reviewStatus: PackReviewStatus | null;
    reviewNote: string | null;
    version: number;
    owner?: string | null;
    items?: { key: string; title: string }[];
    itemsCount?: number;
};

export type TaskCompletionStat = {
    key: string;
    title: string;
    projects: number;
    started: number;
    completed: number;
    completionRate: number;
    avgHoursToComplete: number | null;
};

export type TaskDropOff = {
    key: string;
    title: string;
    started: number;
    stalled: number;
    dropOffRate: number;
};
