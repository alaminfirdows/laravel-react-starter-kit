import { Form, router } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import CatalogActionController from '@/actions/App/Domain/Catalog/Http/Controllers/Admin/CatalogActionController';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { AdminCatalogAction, CatalogActionFormOptions } from '@/types';

const NO_PROMPT = 'none';

type Props = CatalogActionFormOptions & {
    taskKey: string;
    action?: AdminCatalogAction;
    trigger: ReactNode;
};

export function CatalogActionDialog({
    taskKey,
    action,
    trigger,
    actionTypeOptions,
    executorOptions,
    promptOptions,
}: Props) {
    const [open, setOpen] = useState(false);

    const form = action
        ? CatalogActionController.update.form({
              catalogTask: taskKey,
              catalogAction: action.key,
          })
        : CatalogActionController.store.form(taskKey);

    const changeOpen = (value: boolean) => {
        setOpen(value);

        if (value && !promptOptions) {
            router.reload({ only: ['promptOptions'] });
        }
    };

    return (
        <Dialog open={open} onOpenChange={changeOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {action ? 'Edit action' : 'Add action'}
                    </DialogTitle>
                    <DialogDescription>
                        Saving a changed action bumps the task version.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...form}
                    options={{ preserveScroll: true }}
                    transform={(data) => ({
                        ...data,
                        prompt: data.prompt === NO_PROMPT ? null : data.prompt,
                    })}
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing, errors }) => (
                        <FieldGroup>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field data-invalid={!!errors.key}>
                                    <FieldLabel htmlFor="action_key">
                                        Key
                                    </FieldLabel>
                                    <Input
                                        id="action_key"
                                        name="key"
                                        required
                                        readOnly={!!action}
                                        defaultValue={action?.key}
                                        aria-invalid={!!errors.key}
                                    />
                                    <FieldError>{errors.key}</FieldError>
                                </Field>
                                <Field data-invalid={!!errors.title}>
                                    <FieldLabel htmlFor="action_title">
                                        Title
                                    </FieldLabel>
                                    <Input
                                        id="action_title"
                                        name="title"
                                        required
                                        defaultValue={action?.title}
                                        aria-invalid={!!errors.title}
                                    />
                                    <FieldError>{errors.title}</FieldError>
                                </Field>
                                <Field data-invalid={!!errors.type}>
                                    <FieldLabel htmlFor="action_type">
                                        Type
                                    </FieldLabel>
                                    <Select
                                        name="type"
                                        items={actionTypeOptions}
                                        defaultValue={action?.type ?? 'manual'}
                                    >
                                        <SelectTrigger
                                            id="action_type"
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                    </Select>
                                    <FieldError>{errors.type}</FieldError>
                                </Field>
                                <Field data-invalid={!!errors.executor}>
                                    <FieldLabel htmlFor="action_executor">
                                        Executor
                                    </FieldLabel>
                                    <Select
                                        name="executor"
                                        items={executorOptions}
                                        defaultValue={
                                            action?.executor ?? 'user'
                                        }
                                    >
                                        <SelectTrigger
                                            id="action_executor"
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                    </Select>
                                    <FieldError>{errors.executor}</FieldError>
                                </Field>
                            </div>
                            <Field data-invalid={!!errors.prompt}>
                                <FieldLabel htmlFor="action_prompt">
                                    Prompt template
                                </FieldLabel>
                                <Select
                                    name="prompt"
                                    items={[
                                        { value: NO_PROMPT, label: 'None' },
                                        ...(promptOptions ?? []),
                                    ]}
                                    defaultValue={action?.prompt ?? NO_PROMPT}
                                >
                                    <SelectTrigger
                                        id="action_prompt"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                </Select>
                                <FieldError>{errors.prompt}</FieldError>
                            </Field>
                            <Field data-invalid={!!errors.instructions_md}>
                                <FieldLabel htmlFor="action_instructions">
                                    Instructions (Markdown)
                                </FieldLabel>
                                <Textarea
                                    id="action_instructions"
                                    name="instructions_md"
                                    rows={4}
                                    defaultValue={action?.instructionsMd ?? ''}
                                />
                                <FieldError>
                                    {errors.instructions_md}
                                </FieldError>
                            </Field>
                            <Field data-invalid={!!errors.config}>
                                <FieldLabel htmlFor="action_config">
                                    Config (JSON)
                                </FieldLabel>
                                <Textarea
                                    id="action_config"
                                    name="config"
                                    rows={3}
                                    className="font-mono"
                                    defaultValue={action?.config ?? ''}
                                    aria-invalid={!!errors.config}
                                />
                                <FieldError>{errors.config}</FieldError>
                            </Field>
                            <Field orientation="horizontal">
                                <Checkbox
                                    id="action_required"
                                    name="is_required"
                                    value="1"
                                    defaultChecked={action?.isRequired ?? true}
                                />
                                <FieldLabel htmlFor="action_required">
                                    Required
                                </FieldLabel>
                            </Field>
                            <Field orientation="horizontal">
                                <Checkbox
                                    id="action_approval"
                                    name="requires_approval"
                                    value="1"
                                    defaultChecked={action?.requiresApproval}
                                />
                                <FieldLabel htmlFor="action_approval">
                                    Requires approval
                                </FieldLabel>
                            </Field>
                            <DialogFooter>
                                <Button variant="primary" type="submit" disabled={processing}>
                                    Save action
                                </Button>
                            </DialogFooter>
                        </FieldGroup>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
