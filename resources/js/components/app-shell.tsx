import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { CommandPaletteProvider } from '@/components/command-palette';
import { SidebarProvider } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = {
    children: ReactNode;
    variant?: AppVariant;
};

export function AppShell({ children, variant = 'sidebar' }: Props) {
    const isOpen = usePage().props.sidebarOpen;

    if (variant === 'header') {
        return (
            <CommandPaletteProvider>
                <div className="flex min-h-screen w-full flex-col">
                    {children}
                </div>
            </CommandPaletteProvider>
        );
    }

    return (
        <CommandPaletteProvider>
            <SidebarProvider defaultOpen={isOpen}>{children}</SidebarProvider>
        </CommandPaletteProvider>
    );
}
