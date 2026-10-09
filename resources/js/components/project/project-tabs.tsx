import { Link } from '@inertiajs/react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { projectSections } from '@/lib/project-sections';
import { cn } from '@/lib/utils';

/**
 * Section tabs under the top bar. Scrolls sideways on narrow screens.
 */
export function ProjectTabs({
    projectSlug,
    pendingApprovals,
}: {
    projectSlug: string;
    pendingApprovals: number;
}) {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <nav
            aria-label="Project sections"
            className="flex shrink-0 [scrollbar-width:none] gap-1 overflow-x-auto border-b px-3 md:px-4"
        >
            {projectSections(projectSlug).map((section) => {
                const active = section.exact
                    ? isCurrentUrl(section.href)
                    : isCurrentOrParentUrl(section.href);

                return (
                    <Link
                        key={section.title}
                        href={section.href}
                        prefetch
                        aria-current={active ? 'page' : undefined}
                        className={cn(
                            'relative flex h-10 shrink-0 items-center gap-1.5 px-2.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground',
                            'after:absolute after:inset-x-2 after:bottom-[-1px] after:h-0.5 after:rounded-full after:bg-transparent after:transition-colors',
                            active && 'text-foreground after:bg-primary',
                        )}
                    >
                        <section.icon className="size-4" />
                        {section.title}
                        {section.title === 'Approvals' &&
                            pendingApprovals > 0 && (
                                <span className="ml-0.5 rounded-full bg-primary/10 px-1.5 font-mono text-[10px] leading-4 text-primary dark:bg-primary/20">
                                    {pendingApprovals}
                                </span>
                            )}
                    </Link>
                );
            })}
        </nav>
    );
}
