import { useProjectChannel } from '@/hooks/use-project-channel';

export function ProjectChannelListener({ projectId }: { projectId: string }) {
    useProjectChannel(projectId);

    return null;
}
