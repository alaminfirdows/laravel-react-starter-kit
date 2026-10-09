import { Link } from '@inertiajs/react';
import { ChevronRight } from '@/components/animated-icons';
import { TaskStatusIcon } from '@/components/project/task-status-icon';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuAction,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
} from '@/components/ui/sidebar';
import { show } from '@/routes/projects/tasks';
import type { TreeGroup, TreeNode } from '@/types';

function containsTask(node: TreeNode, id?: string): boolean {
    return (
        !!id &&
        (node.id === id ||
            node.children.some((child) => containsTask(child, id)))
    );
}

function Node({
    node,
    projectSlug,
    activeTaskId,
}: {
    node: TreeNode;
    projectSlug: string;
    activeTaskId?: string;
}) {
    const link = (
        <SidebarMenuButton
            asChild
            size="sm"
            className="data-[active=true]:bg-card data-[active=true]:shadow-xs data-[active=true]:ring-1 data-[active=true]:ring-sidebar-border"
            isActive={node.id === activeTaskId}
        >
            <Link href={show({ project: projectSlug, task: node.id })} prefetch>
                <TaskStatusIcon status={node.status} />
                <span className="truncate">{node.title}</span>
            </Link>
        </SidebarMenuButton>
    );

    if (node.children.length === 0) {
        return <SidebarMenuItem>{link}</SidebarMenuItem>;
    }

    return (
        <Collapsible asChild defaultOpen={containsTask(node, activeTaskId)}>
            <SidebarMenuItem>
                {link}
                <CollapsibleTrigger asChild>
                    <SidebarMenuAction
                        className="text-muted-foreground transition-transform data-[state=open]:rotate-90"
                        aria-label={`Toggle ${node.title}`}
                    >
                        <ChevronRight />
                    </SidebarMenuAction>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <SidebarMenuSub>
                        {node.children.map((child) => (
                            <Node
                                key={child.id}
                                node={child}
                                projectSlug={projectSlug}
                                activeTaskId={activeTaskId}
                            />
                        ))}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
}

export function TaskTree({
    groups,
    projectSlug,
    activeTaskId,
}: {
    groups: TreeGroup[];
    projectSlug: string;
    activeTaskId?: string;
}) {
    return groups.map((group) => (
        <SidebarGroup key={group.key}>
            <SidebarGroupLabel className="justify-between">
                <span>{group.name}</span>
                <span className="font-mono tabular-nums">
                    {group.progressPct}%
                </span>
            </SidebarGroupLabel>
            <SidebarMenu>
                {group.tasks.map((node) => (
                    <Node
                        key={node.id}
                        node={node}
                        projectSlug={projectSlug}
                        activeTaskId={activeTaskId}
                    />
                ))}
            </SidebarMenu>
        </SidebarGroup>
    ));
}
