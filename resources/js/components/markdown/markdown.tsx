import ReactMarkdown from 'react-markdown';
import rehypeSanitize from 'rehype-sanitize';
import remarkGfm from 'remark-gfm';
import { cn } from '@/lib/utils';

export function Markdown({
    source,
    className,
}: {
    source: string | null;
    className?: string;
}) {
    if (!source) {
        return null;
    }

    return (
        <div
            className={cn(
                'space-y-3 text-sm leading-6 [&_a]:text-primary [&_a]:underline [&_code]:rounded [&_code]:bg-muted [&_code]:px-1 [&_h2]:text-base [&_h2]:font-semibold [&_h3]:font-semibold [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5',
                className,
            )}
        >
            <ReactMarkdown
                remarkPlugins={[remarkGfm]}
                rehypePlugins={[rehypeSanitize]}
                components={{
                    a: ({ node: _node, ...props }) => (
                        <a
                            {...props}
                            target="_blank"
                            rel="noreferrer noopener"
                        />
                    ),
                }}
            >
                {source}
            </ReactMarkdown>
        </div>
    );
}
