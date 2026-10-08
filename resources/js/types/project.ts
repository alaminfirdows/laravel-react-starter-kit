export type ProjectPhase = 'planning' | 'developing' | 'selling';
export type ProjectStatus = 'draft' | 'active' | 'archived';
export type ProjectSetupStep = 'identity' | 'business' | 'market' | 'goals';
export type TaskStatus =
    | 'locked'
    | 'todo'
    | 'in_progress'
    | 'blocked'
    | 'awaiting_approval'
    | 'done'
    | 'skipped';

export type Option<T extends string = string> = { value: T; label: string };
export type PhaseOption = Option<ProjectPhase> & { description: string };

export type TreeNode = {
    id: string;
    title: string;
    status: TaskStatus;
    progressPct: number;
    depth: number;
    children: TreeNode[];
};

export type TreeGroup = {
    key: string;
    name: string;
    icon: string | null;
    progressPct: number;
    tasks: TreeNode[];
};

export type ProjectTree = { progressPct: number; groups: TreeGroup[] };

export type Project = {
    id: string;
    slug: string;
    name: string;
    oneLiner: string | null;
    phase: ProjectPhase;
    phaseLabel: string;
    status: ProjectStatus;
    logoUrl: string | null;
    progressPct?: number;
    setupStep?: ProjectSetupStep;
};

export type ProjectGoal = {
    title: string;
    metric?: string | null;
    target?: string | null;
    due?: string | null;
};

export type ProjectSetup = {
    slug: string;
    status: ProjectStatus;
    logoUrl: string | null;
    name: string;
    one_liner: string;
    description_md: string;
    website_url: string;
    business_model: string;
    stage: string;
    industry: string;
    pricing_model: string;
    primary_market: string;
    target_customer: string;
    problem_statement: string;
    solution_summary: string;
    goals: ProjectGoal[];
};

export type TaskSummary = {
    id: string;
    title: string;
    summary: string | null;
    status: TaskStatus;
    progressPct: number;
    isLeaf?: boolean;
};

export type TaskAction = {
    id: string;
    title: string;
    type: string;
    executor: string;
    executorLabel: string;
    status: string;
    instructionsMd: string | null;
    isRequired: boolean;
    prompt: string;
};

export type Task = {
    id: string;
    title: string;
    summary: string | null;
    bodyMd: string | null;
    status: TaskStatus;
    statusLabel: string;
    priority: string;
    verification: string;
    progressPct: number;
    depth: number;
    isLeaf: boolean;
    completedAt: string | null;
    ancestors: { id: string; title: string }[];
    parent: TaskSummary | null;
    children: TaskSummary[];
    actions: TaskAction[];
};

export type Activity = {
    id: number;
    event: string;
    actorType: 'user' | 'agent' | 'system';
    clientName: string | null;
    channel: string;
    properties: Record<string, unknown>;
    createdAt: string;
};

export type ProjectPageProps = {
    project: Project;
    tree: ProjectTree;
    can: { update: boolean };
};
