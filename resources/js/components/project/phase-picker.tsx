import { Hammer, Lightbulb, Rocket } from 'lucide-react';
import {
    Field,
    FieldContent,
    FieldDescription,
    FieldLabel,
    FieldTitle,
} from '@/components/ui/field';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import type { PhaseOption, ProjectPhase } from '@/types';

const icons: Record<ProjectPhase, typeof Lightbulb> = {
    planning: Lightbulb,
    developing: Hammer,
    selling: Rocket,
};

export function PhasePicker({
    phases,
    name,
}: {
    phases: PhaseOption[];
    name: string;
}) {
    return (
        <RadioGroup
            name={name}
            defaultValue={phases[0]?.value}
            className="grid gap-3 md:grid-cols-3"
        >
            {phases.map((phase) => {
                const Icon = icons[phase.value];

                return (
                    <FieldLabel
                        key={phase.value}
                        htmlFor={`phase-${phase.value}`}
                    >
                        <Field orientation="horizontal">
                            <FieldContent>
                                <Icon className="size-5" />
                                <FieldTitle>{phase.label}</FieldTitle>
                                <FieldDescription>
                                    {phase.description}
                                </FieldDescription>
                            </FieldContent>
                            <RadioGroupItem
                                id={`phase-${phase.value}`}
                                value={phase.value}
                            />
                        </Field>
                    </FieldLabel>
                );
            })}
        </RadioGroup>
    );
}
