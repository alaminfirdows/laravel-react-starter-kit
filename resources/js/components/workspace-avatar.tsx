import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';

export function WorkspaceAvatar({
    name,
    logoUrl,
    className,
}: {
    name: string;
    logoUrl: string | null;
    className?: string;
}) {
    const getInitials = useInitials();

    return (
        <Avatar className={cn('size-8 rounded-md', className)}>
            {logoUrl && (
                <AvatarImage
                    src={logoUrl}
                    alt={name}
                    className="object-cover"
                />
            )}
            <AvatarFallback className="rounded-md bg-sidebar-primary text-xs text-sidebar-primary-foreground">
                {getInitials(name)}
            </AvatarFallback>
        </Avatar>
    );
}
