export default function Heading({
    title,
    description,
    variant = 'default',
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
}) {
    return (
        <header
            className={variant === 'small' ? '' : 'mb-6 flex flex-col gap-1'}
        >
            <h2
                className={
                    variant === 'small'
                        ? 'mb-0.5 text-sm font-semibold'
                        : 'text-lg font-semibold tracking-tight'
                }
            >
                {title}
            </h2>
            {description && (
                <p className="text-sm text-muted-foreground">{description}</p>
            )}
        </header>
    );
}
