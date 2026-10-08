import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Props = {
    counts: { tasks: number; actions: number; prompts: number; packs: number };
};

export default function AdminIndex({ counts }: Props) {
    const stats = [
        { label: 'Catalog tasks', value: counts.tasks },
        { label: 'Actions', value: counts.actions },
        { label: 'Prompt templates', value: counts.prompts },
        { label: 'Packs', value: counts.packs },
    ];

    return (
        <>
            <Head title="Admin" />
            <div className="space-y-6">
                <Heading variant="small" title="Overview" />
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {stats.map((stat) => (
                        <Card key={stat.label}>
                            <CardHeader>
                                <CardDescription>{stat.label}</CardDescription>
                                <CardTitle className="text-2xl">
                                    {stat.value}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                    ))}
                </div>
            </div>
        </>
    );
}
