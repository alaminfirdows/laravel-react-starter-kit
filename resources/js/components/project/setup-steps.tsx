import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';
import { edit } from '@/routes/projects/setup';
import type { Option } from '@/types';

export function SetupSteps({
    steps,
    current,
    projectSlug,
}: {
    steps: Option[];
    current: string;
    projectSlug: string;
}) {
    const currentIndex = steps.findIndex((step) => step.value === current);

    return (
        <ol className="flex items-center gap-2">
            {steps.map((step, i) => {
                const isCurrent = i === currentIndex;
                const isDone = i < currentIndex;

                return (
                    <li
                        key={step.value}
                        className="flex flex-1 items-center gap-2 last:flex-none"
                    >
                        <Link
                            href={edit({
                                project: projectSlug,
                                step: step.value,
                            })}
                            aria-current={isCurrent ? 'step' : undefined}
                            className="group flex items-center gap-2 text-sm"
                        >
                            <span
                                className={cn(
                                    'flex size-6 shrink-0 items-center justify-center rounded-full border font-mono text-xs',
                                    isCurrent &&
                                        'border-primary bg-primary text-primary-foreground',
                                    isDone &&
                                        'border-primary/30 bg-primary/10 text-primary',
                                    !isCurrent &&
                                        !isDone &&
                                        'text-muted-foreground',
                                )}
                            >
                                {isDone ? (
                                    <Check className="size-3.5" />
                                ) : (
                                    i + 1
                                )}
                            </span>
                            <span
                                className={cn(
                                    'hidden sm:inline',
                                    isCurrent
                                        ? 'font-medium text-foreground'
                                        : 'text-muted-foreground group-hover:text-foreground',
                                )}
                            >
                                {step.label}
                            </span>
                        </Link>
                        {i < steps.length - 1 && (
                            <span
                                className={cn(
                                    'h-px flex-1',
                                    isDone ? 'bg-primary/30' : 'bg-border',
                                )}
                            />
                        )}
                    </li>
                );
            })}
        </ol>
    );
}
