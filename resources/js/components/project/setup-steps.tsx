import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
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
        <ol className="flex flex-wrap items-center gap-2">
            {steps.map((step, i) => (
                <li key={step.value}>
                    <Badge
                        asChild
                        variant={i === currentIndex ? 'default' : 'outline'}
                    >
                        <Link
                            href={edit({
                                project: projectSlug,
                                step: step.value,
                            })}
                        >
                            {i < currentIndex ? <Check /> : `${i + 1}.`}
                            {step.label}
                        </Link>
                    </Badge>
                </li>
            ))}
        </ol>
    );
}
