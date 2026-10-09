import { router, usePage } from '@inertiajs/react';
import { FolderKanban, Monitor, Moon, Search, Sun, User } from 'lucide-react';
import type { ReactNode } from 'react';
import { createContext, useContext, useEffect, useState } from 'react';
import { WorkspaceAvatar } from '@/components/workspace-avatar';
import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
    CommandShortcut,
} from '@/components/ui/command';
import { useAppearance } from '@/hooks/use-appearance';
import { useMainNav } from '@/hooks/use-main-nav';
import { cn, toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import { projectSections } from '@/lib/project-sections';
import { show as showProject } from '@/routes/projects';
import type { Project } from '@/types';

type CommandPaletteContextValue = {
    open: boolean;
    setOpen: (open: boolean) => void;
};

const CommandPaletteContext = createContext<CommandPaletteContextValue>({
    open: false,
    setOpen: () => {},
});

export function useCommandPalette(): CommandPaletteContextValue {
    return useContext(CommandPaletteContext);
}

/**
 * Global ⌘K palette: jump to pages, projects, workspaces, or switch theme.
 */
export function CommandPaletteProvider({ children }: { children: ReactNode }) {
    const [open, setOpen] = useState(false);

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'k' && (event.metaKey || event.ctrlKey)) {
                event.preventDefault();
                setOpen((value) => !value);
            }
        };

        document.addEventListener('keydown', onKeyDown);

        return () => document.removeEventListener('keydown', onKeyDown);
    }, []);

    return (
        <CommandPaletteContext.Provider value={{ open, setOpen }}>
            {children}
            <CommandPalette open={open} onOpenChange={setOpen} />
        </CommandPaletteContext.Provider>
    );
}

function CommandPalette({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { workspaces, currentWorkspace, paletteProjects, project } = usePage<{
        project?: Project;
    }>().props;
    const nav = useMainNav();
    const { updateAppearance } = useAppearance();

    useEffect(() => {
        if (open && paletteProjects === undefined && currentWorkspace) {
            router.reload({ only: ['paletteProjects'] });
        }
    }, [open, paletteProjects, currentWorkspace]);

    const go = (href: Parameters<typeof toUrl>[0]) => {
        onOpenChange(false);
        router.visit(toUrl(href));
    };

    const sections = project ? projectSections(project.slug) : [];

    return (
        <CommandDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Command menu"
            description="Search pages, projects, and workspaces"
            showCloseButton={false}
            className="top-[20%] translate-y-0 sm:max-w-xl"
        >
            <CommandInput placeholder="Search or jump to…" />
            <CommandList className="max-h-[min(60vh,420px)]">
                <CommandEmpty>No results.</CommandEmpty>

                {project && (
                    <CommandGroup heading={project.name}>
                        {sections.map((item) => (
                            <CommandItem
                                key={item.title}
                                value={`${project.name} ${item.title}`}
                                onSelect={() => go(item.href)}
                            >
                                <item.icon />
                                {item.title}
                            </CommandItem>
                        ))}
                    </CommandGroup>
                )}

                <CommandGroup heading="Go to">
                    {[...nav.workspace, ...nav.manage].map((item) => (
                        <CommandItem
                            key={item.title}
                            onSelect={() => go(item.href)}
                        >
                            {item.icon && <item.icon />}
                            {item.title}
                        </CommandItem>
                    ))}
                    <CommandItem onSelect={() => go(editProfile())}>
                        <User />
                        Profile settings
                    </CommandItem>
                </CommandGroup>

                <CommandGroup heading="Projects">
                    {paletteProjects === undefined ? (
                        <CommandItem disabled value="loading projects">
                            <FolderKanban />
                            <span className="text-muted-foreground">
                                Loading projects…
                            </span>
                        </CommandItem>
                    ) : (
                        paletteProjects.map((item) => (
                            <CommandItem
                                key={item.id}
                                value={`project ${item.name}`}
                                onSelect={() =>
                                    go(showProject({ project: item.slug }))
                                }
                            >
                                <FolderKanban />
                                {item.name}
                            </CommandItem>
                        ))
                    )}
                </CommandGroup>

                {workspaces.length > 1 && (
                    <CommandGroup heading="Switch workspace">
                        {workspaces.map((workspace) => (
                            <CommandItem
                                key={workspace.id}
                                value={`workspace ${workspace.name}`}
                                onSelect={() => go(dashboard(workspace.slug))}
                            >
                                <WorkspaceAvatar
                                    name={workspace.name}
                                    logoUrl={workspace.logoUrl}
                                    className="size-5 text-[10px]"
                                />
                                {workspace.name}
                                {workspace.id === currentWorkspace?.id && (
                                    <CommandShortcut>Current</CommandShortcut>
                                )}
                            </CommandItem>
                        ))}
                    </CommandGroup>
                )}

                <CommandSeparator />
                <CommandGroup heading="Theme">
                    {(
                        [
                            ['light', Sun, 'Light'],
                            ['dark', Moon, 'Dark'],
                            ['system', Monitor, 'System'],
                        ] as const
                    ).map(([value, Icon, label]) => (
                        <CommandItem
                            key={value}
                            value={`theme ${label}`}
                            onSelect={() => {
                                updateAppearance(value);
                                onOpenChange(false);
                            }}
                        >
                            <Icon />
                            {label} theme
                        </CommandItem>
                    ))}
                </CommandGroup>
            </CommandList>
        </CommandDialog>
    );
}

/**
 * Search field look-alike that opens the palette; shows the ⌘K hint.
 */
export function CommandPaletteTrigger({ className }: { className?: string }) {
    const { setOpen } = useCommandPalette();

    return (
        <button
            type="button"
            onClick={() => setOpen(true)}
            aria-label="Open command menu"
            className={cn(
                'flex h-8 cursor-pointer items-center gap-2 rounded-md border bg-card px-2.5 text-sm text-muted-foreground shadow-xs transition-colors hover:bg-muted hover:text-foreground dark:bg-input/30',
                className,
            )}
        >
            <Search className="size-3.5" />
            <span className="hidden flex-1 text-left sm:inline">Search…</span>
            <kbd className="pointer-events-none hidden h-5 items-center gap-0.5 rounded border bg-muted px-1.5 font-mono text-[10px] font-medium sm:inline-flex">
                <span className="text-xs">⌘</span>K
            </kbd>
        </button>
    );
}
