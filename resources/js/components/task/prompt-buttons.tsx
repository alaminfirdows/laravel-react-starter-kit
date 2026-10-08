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
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useClipboard } from '@/hooks/use-clipboard';

export function PromptButtons({ prompt }: { prompt: string }) {
    const [, copy] = useClipboard();

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
            <Tooltip>
                <TooltipTrigger asChild>
                    <span>
                        <Button size="sm" variant="outline" disabled>
                            <ExternalLink /> Open in Claude
                        </Button>
                    </span>
                </TooltipTrigger>
                <TooltipContent>Coming soon — connect Claude</TooltipContent>
            </Tooltip>
        </div>
    );
}
