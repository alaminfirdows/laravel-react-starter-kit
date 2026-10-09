import { Form } from '@inertiajs/react';
import { useState } from 'react';
import type { ComponentProps, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
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
import { Textarea } from '@/components/ui/textarea';

export type ResearchField = {
    name: string;
    label: string;
    type?: 'text' | 'url' | 'date' | 'textarea';
    required?: boolean;
    placeholder?: string;
};

const maxLengths: Record<string, number | undefined> = {
    text: 255,
    url: 2048,
    textarea: 20000,
};

/**
 * Dialog with one create or edit form for a research row (interview, competitor).
 */
export function ResearchFormDialog({
    trigger,
    title,
    description,
    submitLabel,
    form,
    fields,
    defaults = {},
}: {
    trigger: ReactNode;
    title: string;
    description: string;
    submitLabel: string;
    form: Pick<ComponentProps<typeof Form>, 'action' | 'method'>;
    fields: ResearchField[];
    defaults?: Record<string, string | null>;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <Form
                    {...form}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    resetOnSuccess
                >
                    {({ processing, errors }) => (
                        <FieldGroup>
                            {fields.map((field) => {
                                const id = `research-${field.name}`;
                                const error = errors[field.name];

                                return (
                                    <Field
                                        key={field.name}
                                        data-invalid={!!error}
                                    >
                                        <FieldLabel htmlFor={id}>
                                            {field.label}
                                        </FieldLabel>
                                        {field.type === 'textarea' ? (
                                            <Textarea
                                                id={id}
                                                name={field.name}
                                                maxLength={maxLengths.textarea}
                                                defaultValue={
                                                    defaults[field.name] ?? ''
                                                }
                                                placeholder={field.placeholder}
                                                aria-invalid={!!error}
                                            />
                                        ) : (
                                            <Input
                                                id={id}
                                                name={field.name}
                                                type={field.type ?? 'text'}
                                                required={field.required}
                                                maxLength={
                                                    maxLengths[
                                                        field.type ?? 'text'
                                                    ]
                                                }
                                                defaultValue={
                                                    defaults[field.name] ?? ''
                                                }
                                                placeholder={field.placeholder}
                                                className={
                                                    field.type === 'date'
                                                        ? 'w-fit'
                                                        : undefined
                                                }
                                                aria-invalid={!!error}
                                            />
                                        )}
                                        <FieldError>{error}</FieldError>
                                    </Field>
                                );
                            })}
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {submitLabel}
                                </Button>
                            </DialogFooter>
                        </FieldGroup>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
