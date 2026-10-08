import { Copy, ExternalLink, Eye } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { useClipboard } from '@/hooks/use-clipboard';
import type { DeepLink } from '@/types';

export function PromptButtons({
    prompt,
    deepLink,
}: {
    prompt: string;
    deepLink: DeepLink;
}) {
    const [, copy] = useClipboard();

    const openInClaude = async () => {
        if (deepLink.url) {
            window.location.href = deepLink.url;
            toast.info('Claude opens with the prompt. Press Send to start.');

            return;
        }

        if (await copy(deepLink.launcher)) {
            toast.info(
                'Prompt too long for a link. Copied — paste it in Claude.',
            );
        } else {
            toast.error('Copy failed');
        }
    };

    const copyPrompt = async () => {
        if (await copy(prompt)) {
            toast.success('Prompt copied');
        } else {
            toast.error('Copy failed');
        }
    };

    return (
        <div className="flex flex-wrap gap-2">
            <Button size="sm" onClick={copyPrompt}>
                <Copy /> Copy prompt
            </Button>
            <Dialog>
                <DialogTrigger asChild>
                    <Button size="sm" variant="outline">
                        <Eye /> Preview
                    </Button>
                </DialogTrigger>
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Prompt</DialogTitle>
                    </DialogHeader>
                    <ScrollArea className="max-h-[60vh] rounded-md bg-muted">
                        <pre className="p-3 text-xs whitespace-pre-wrap">
                            {prompt}
                        </pre>
                    </ScrollArea>
                </DialogContent>
            </Dialog>
            <Button size="sm" variant="outline" onClick={openInClaude}>
                <ExternalLink />
                {deepLink.target === 'cowork'
                    ? 'Open in Cowork'
                    : 'Open in Claude'}
            </Button>
        </div>
    );
}
