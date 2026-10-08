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

export type DeepLink = {
    target: 'chat' | 'cowork';
    launcher: string;
    url: string | null;
};

export type ActionRun = {
    id: string;
    status: 'started' | 'succeeded' | 'failed' | 'cancelled';
    channel: string;
    actorType: 'user' | 'agent' | 'system';
    clientName: string | null;
    outputMd: string | null;
    error: string | null;
    usage: { model: string | null; totalTokens: number } | null;
    startedAt: string | null;
    finishedAt: string | null;
};

export type Evidence = {
    id: string;
    kind: string;
    label: string;
    value: string | null;
    criterionKey: string | null;
    passed: boolean | null;
    createdAt: string | null;
};

export type Approval = {
    id: string;
    status: 'pending' | 'approved' | 'rejected' | 'expired';
    summaryMd: string;
    requestedByClient: string | null;
    decisionNote: string | null;
    createdAt: string | null;
    decidedAt?: string | null;
    subject?: { title: string; taskId: string; taskTitle: string };
};

export type TaskAction = {
    id: string;
    title: string;
    type: string;
    executor: string;
    executorLabel: string;
    runsInApp: boolean;
    status: string;
    statusLabel: string;
    instructionsMd: string | null;
    isRequired: boolean;
    prompt: string;
    deepLink: DeepLink;
    runs: ActionRun[];
    evidence: Evidence[];
    pendingApproval: Approval | null;
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
    hasActiveRun: boolean;
    assignee?: { id: string; name: string } | null;
};

export type Activity = {
    id: number;
    event: string;
    actorType: 'user' | 'agent' | 'system';
    actorName?: string | null;
    clientName: string | null;
    project?: { name: string; slug: string } | null;
    channel: string;
    properties: Record<string, unknown>;
    createdAt: string;
};

export type ProjectPageProps = {
    project: Project;
    tree: ProjectTree;
    pendingApprovals: number;
    can: { update: boolean };
};

export type McpConnection = {
    id: string;
    clientName: string;
    workspaceName: string | null;
    createdAt: string | null;
    expiresAt: string | null;
    userId: string | null;
    userName: string | null;
    canRevoke?: boolean;
};

export type TaskComment = {
    id: string;
    bodyMd: string;
    authorType: 'user' | 'agent' | 'system';
    authorName: string | null;
    clientName: string | null;
    resolvedAt: string | null;
    createdAt: string;
};

export type SelectItemOption = { value: string; label: string };

export type ActivityFilterValues = {
    actor: string | null;
    channel: string | null;
    event: string | null;
};

export type ActivityLogProps = {
    activity: {
        data: Activity[];
        links: { prev: string | null; next: string | null };
    };
    filters: ActivityFilterValues;
    options: {
        actors: SelectItemOption[];
        channels: SelectItemOption[];
        events: SelectItemOption[];
    };
};
