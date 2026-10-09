import type {
    ComponentType,
    CSSProperties,
    ForwardRefExoticComponent,
    HTMLAttributes,
    RefAttributes,
} from 'react';
import { useEffect, useRef } from 'react';
import { ActivityIcon as ActivityIconSource } from '@/components/ui/activity-icon';
import { ArrowLeftIcon as ArrowLeftIconSource } from '@/components/ui/arrow-left-icon';
import { ArrowRightIcon as ArrowRightIconSource } from '@/components/ui/arrow-right-icon';
import { BanIcon as BanIconSource } from '@/components/ui/ban-icon';
import { BellIcon as BellIconSource } from '@/components/ui/bell-icon';
import { BoldIcon as BoldIconSource } from '@/components/ui/bold-icon';
import { BookOpenIcon as BookOpenIconSource } from '@/components/ui/book-open-icon';
import { BotIcon as BotIconSource } from '@/components/ui/bot-icon';
import { Building2Icon as Building2IconSource } from '@/components/ui/building-2-icon';
import { CalendarXIcon as CalendarXIconSource } from '@/components/ui/calendar-x-icon';
import { ChartBarIcon as ChartBarIconSource } from '@/components/ui/chart-bar-icon';
import { CheckCheckIcon as CheckCheckIconSource } from '@/components/ui/check-check-icon';
import { CheckIcon as CheckIconSource } from '@/components/ui/check-icon';
import { ChevronDownIcon as ChevronDownIconSource } from '@/components/ui/chevron-down-icon';
import { ChevronRightIcon as ChevronRightIconSource } from '@/components/ui/chevron-right-icon';
import { ChevronUpIcon as ChevronUpIconSource } from '@/components/ui/chevron-up-icon';
import { ChevronsUpDownIcon as ChevronsUpDownIconSource } from '@/components/ui/chevrons-up-down-icon';
import { CircleAlertIcon as CircleAlertIconSource } from '@/components/ui/circle-alert-icon';
import { CircleCheckIcon as CircleCheckIconSource } from '@/components/ui/circle-check-icon';
import { CircleDotIcon as CircleDotIconSource } from '@/components/ui/circle-dot-icon';
import { ClipboardCheckIcon as ClipboardCheckIconSource } from '@/components/ui/clipboard-check-icon';
import { ClipboardListIcon as ClipboardListIconSource } from '@/components/ui/clipboard-list-icon';
import { CopyIcon as CopyIconSource } from '@/components/ui/copy-icon';
import { CornerLeftUpIcon as CornerLeftUpIconSource } from '@/components/ui/corner-left-up-icon';
import { EllipsisIcon as EllipsisIconSource } from '@/components/ui/ellipsis-icon';
import { ExternalLinkIcon as ExternalLinkIconSource } from '@/components/ui/external-link-icon';
import { EyeIcon as EyeIconSource } from '@/components/ui/eye-icon';
import { EyeOffIcon as EyeOffIconSource } from '@/components/ui/eye-off-icon';
import { FileTextIcon as FileTextIconSource } from '@/components/ui/file-text-icon';
import { FolderIcon as FolderIconSource } from '@/components/ui/folder-icon';
import { HammerIcon as HammerIconSource } from '@/components/ui/hammer-icon';
import { Heading2Icon as Heading2IconSource } from '@/components/ui/heading-2-icon';
import { HistoryIcon as HistoryIconSource } from '@/components/ui/history-icon';
import { InboxIcon as InboxIconSource } from '@/components/ui/inbox-icon';
import { InfoIcon as InfoIconSource } from '@/components/ui/info-icon';
import { ItalicIcon as ItalicIconSource } from '@/components/ui/italic-icon';
import { KanbanIcon as KanbanIconSource } from '@/components/ui/kanban-icon';
import { KeyRoundIcon as KeyRoundIconSource } from '@/components/ui/key-round-icon';
import { LayersIcon as LayersIconSource } from '@/components/ui/layers-icon';
import { LayoutDashboardIcon as LayoutDashboardIconSource } from '@/components/ui/layout-dashboard-icon';
import { LayoutGridIcon as LayoutGridIconSource } from '@/components/ui/layout-grid-icon';
import { LightbulbIcon as LightbulbIconSource } from '@/components/ui/lightbulb-icon';
import { ListChecksIcon as ListChecksIconSource } from '@/components/ui/list-checks-icon';
import { ListIcon as ListIconSource } from '@/components/ui/list-icon';
import { ListOrderedIcon as ListOrderedIconSource } from '@/components/ui/list-ordered-icon';
import { ListTreeIcon as ListTreeIconSource } from '@/components/ui/list-tree-icon';
import { LoaderCircleIcon as LoaderCircleIconSource } from '@/components/ui/loader-circle-icon';
import { LockIcon as LockIconSource } from '@/components/ui/lock-icon';
import { LogOutIcon as LogOutIconSource } from '@/components/ui/log-out-icon';
import { MailIcon as MailIconSource } from '@/components/ui/mail-icon';
import { MenuIcon as MenuIconSource } from '@/components/ui/menu-icon';
import { MessageSquareIcon as MessageSquareIconSource } from '@/components/ui/message-square-icon';
import { MessageSquareTextIcon as MessageSquareTextIconSource } from '@/components/ui/message-square-text-icon';
import { MessagesSquareIcon as MessagesSquareIconSource } from '@/components/ui/messages-square-icon';
import { MinusIcon as MinusIconSource } from '@/components/ui/minus-icon';
import { MonitorIcon as MonitorIconSource } from '@/components/ui/monitor-icon';
import { MoonIcon as MoonIconSource } from '@/components/ui/moon-icon';
import { PackageIcon as PackageIconSource } from '@/components/ui/package-icon';
import { PanelLeftCloseIcon as PanelLeftCloseIconSource } from '@/components/ui/panel-left-close-icon';
import { PanelLeftOpenIcon as PanelLeftOpenIconSource } from '@/components/ui/panel-left-open-icon';
import { PencilIcon as PencilIconSource } from '@/components/ui/pencil-icon';
import { PlugIcon as PlugIconSource } from '@/components/ui/plug-icon';
import { PlusIcon as PlusIconSource } from '@/components/ui/plus-icon';
import { RefreshCwIcon as RefreshCwIconSource } from '@/components/ui/refresh-cw-icon';
import { RocketIcon as RocketIconSource } from '@/components/ui/rocket-icon';
import { RotateCcwIcon as RotateCcwIconSource } from '@/components/ui/rotate-ccw-icon';
import { ScanLineIcon as ScanLineIconSource } from '@/components/ui/scan-line-icon';
import { SearchIcon as SearchIconSource } from '@/components/ui/search-icon';
import { SettingsIcon as SettingsIconSource } from '@/components/ui/settings-icon';
import { ShieldAlertIcon as ShieldAlertIconSource } from '@/components/ui/shield-alert-icon';
import { ShieldCheckIcon as ShieldCheckIconSource } from '@/components/ui/shield-check-icon';
import { SparklesIcon as SparklesIconSource } from '@/components/ui/sparkles-icon';
import { SunIcon as SunIconSource } from '@/components/ui/sun-icon';
import { Trash2Icon as Trash2IconSource } from '@/components/ui/trash-2-icon';
import { UserIcon as UserIconSource } from '@/components/ui/user-icon';
import { UsersIcon as UsersIconSource } from '@/components/ui/users-icon';
import { XIcon as XIconSource } from '@/components/ui/x-icon';
import { ZapIcon as ZapIconSource } from '@/components/ui/zap-icon';
import { cn } from '@/lib/utils';

type AnimatedIconHandle = {
    startAnimation: () => void;
    stopAnimation: () => void;
};

type AnimatedIconSource = ForwardRefExoticComponent<
    Omit<HTMLAttributes<HTMLDivElement>, 'color'> & {
        size?: number;
        color?: string;
        duration?: number;
        isAnimated?: boolean;
    } & RefAttributes<AnimatedIconHandle>
>;

export type AppIconProps = {
    className?: string;
    size?: number;
    color?: string;
    style?: CSSProperties;
    'aria-hidden'?: boolean | 'true' | 'false';
};

export type AppIcon = ComponentType<AppIconProps>;

/**
 * Closest interactive ancestor whose hover or focus plays the icon animation.
 */
const TRIGGER_SELECTOR = [
    'button',
    'a',
    'label',
    'summary',
    '[role="button"]',
    '[role="menuitem"]',
    '[role="menuitemradio"]',
    '[role="menuitemcheckbox"]',
    '[role="option"]',
    '[role="tab"]',
    '[data-icon-trigger]',
].join(',');

const SIZE_CLASS_PATTERN = /(^|\s)(size|h|w)-/;

/**
 * Wraps an animated icon so it keeps the lucide-react call signature
 * (`className` sizing, component-as-prop usage) and plays its animation
 * when the surrounding button, link, or menu item is hovered or focused.
 */
function withParentHover(Icon: AnimatedIconSource): AppIcon {
    function AnimatedIcon({ className, ...props }: AppIconProps) {
        const anchorRef = useRef<HTMLSpanElement>(null);
        const iconRef = useRef<AnimatedIconHandle>(null);

        useEffect(() => {
            const trigger =
                anchorRef.current?.parentElement?.closest<HTMLElement>(
                    TRIGGER_SELECTOR,
                );

            if (!trigger) {
                return;
            }

            const start = (): void => iconRef.current?.startAnimation();
            const stop = (): void => iconRef.current?.stopAnimation();

            trigger.addEventListener('mouseenter', start);
            trigger.addEventListener('mouseleave', stop);
            trigger.addEventListener('focusin', start);
            trigger.addEventListener('focusout', stop);

            return () => {
                trigger.removeEventListener('mouseenter', start);
                trigger.removeEventListener('mouseleave', stop);
                trigger.removeEventListener('focusin', start);
                trigger.removeEventListener('focusout', stop);
            };
        }, []);

        return (
            <span ref={anchorRef} className="contents">
                <Icon
                    ref={iconRef}
                    className={cn(
                        'shrink-0',
                        className &&
                            SIZE_CLASS_PATTERN.test(className) &&
                            '[&>svg]:size-full',
                        className,
                    )}
                    onMouseEnter={() => iconRef.current?.startAnimation()}
                    onMouseLeave={() => iconRef.current?.stopAnimation()}
                    {...props}
                />
            </span>
        );
    }

    AnimatedIcon.displayName = `Animated(${Icon.displayName ?? 'Icon'})`;

    return AnimatedIcon;
}

export const Activity = /* @__PURE__ */ withParentHover(ActivityIconSource);
export const ActivityIcon = /* @__PURE__ */ withParentHover(ActivityIconSource);
export const AlertCircleIcon = /* @__PURE__ */ withParentHover(
    CircleAlertIconSource,
);
export const ArrowLeft = /* @__PURE__ */ withParentHover(ArrowLeftIconSource);
export const ArrowRight = /* @__PURE__ */ withParentHover(ArrowRightIconSource);
export const Ban = /* @__PURE__ */ withParentHover(BanIconSource);
export const BarChart3 = /* @__PURE__ */ withParentHover(ChartBarIconSource);
export const Bell = /* @__PURE__ */ withParentHover(BellIconSource);
export const Bold = /* @__PURE__ */ withParentHover(BoldIconSource);
export const BookOpen = /* @__PURE__ */ withParentHover(BookOpenIconSource);
export const Bot = /* @__PURE__ */ withParentHover(BotIconSource);
export const Building2 = /* @__PURE__ */ withParentHover(Building2IconSource);
export const CalendarX = /* @__PURE__ */ withParentHover(CalendarXIconSource);
export const ChartBar = /* @__PURE__ */ withParentHover(ChartBarIconSource);
export const Check = /* @__PURE__ */ withParentHover(CheckIconSource);
export const CheckCheck = /* @__PURE__ */ withParentHover(CheckCheckIconSource);
export const CheckCircle2 = /* @__PURE__ */ withParentHover(
    CircleCheckIconSource,
);
export const CheckIcon = /* @__PURE__ */ withParentHover(CheckIconSource);
export const ChevronDown = /* @__PURE__ */ withParentHover(
    ChevronDownIconSource,
);
export const ChevronDownIcon = /* @__PURE__ */ withParentHover(
    ChevronDownIconSource,
);
export const ChevronRight = /* @__PURE__ */ withParentHover(
    ChevronRightIconSource,
);
export const ChevronRightIcon = /* @__PURE__ */ withParentHover(
    ChevronRightIconSource,
);
export const ChevronUp = /* @__PURE__ */ withParentHover(ChevronUpIconSource);
export const ChevronUpIcon =
    /* @__PURE__ */ withParentHover(ChevronUpIconSource);
export const ChevronsUpDown = /* @__PURE__ */ withParentHover(
    ChevronsUpDownIconSource,
);
export const CircleAlert = /* @__PURE__ */ withParentHover(
    CircleAlertIconSource,
);
export const CircleCheck = /* @__PURE__ */ withParentHover(
    CircleCheckIconSource,
);
export const CircleDot = /* @__PURE__ */ withParentHover(CircleDotIconSource);
export const CircleSlash = /* @__PURE__ */ withParentHover(BanIconSource);
export const ClipboardCheck = /* @__PURE__ */ withParentHover(
    ClipboardCheckIconSource,
);
export const ClipboardList = /* @__PURE__ */ withParentHover(
    ClipboardListIconSource,
);
export const Copy = /* @__PURE__ */ withParentHover(CopyIconSource);
export const CornerLeftUp = /* @__PURE__ */ withParentHover(
    CornerLeftUpIconSource,
);
export const Ellipsis = /* @__PURE__ */ withParentHover(EllipsisIconSource);
export const ExternalLink = /* @__PURE__ */ withParentHover(
    ExternalLinkIconSource,
);
export const Eye = /* @__PURE__ */ withParentHover(EyeIconSource);
export const EyeOff = /* @__PURE__ */ withParentHover(EyeOffIconSource);
export const FileText = /* @__PURE__ */ withParentHover(FileTextIconSource);
export const Folder = /* @__PURE__ */ withParentHover(FolderIconSource);
export const FolderKanban = /* @__PURE__ */ withParentHover(KanbanIconSource);
export const Hammer = /* @__PURE__ */ withParentHover(HammerIconSource);
export const Heading2 = /* @__PURE__ */ withParentHover(Heading2IconSource);
export const History = /* @__PURE__ */ withParentHover(HistoryIconSource);
export const Inbox = /* @__PURE__ */ withParentHover(InboxIconSource);
export const Info = /* @__PURE__ */ withParentHover(InfoIconSource);
export const Italic = /* @__PURE__ */ withParentHover(ItalicIconSource);
export const Kanban = /* @__PURE__ */ withParentHover(KanbanIconSource);
export const KeyRound = /* @__PURE__ */ withParentHover(KeyRoundIconSource);
export const Layers = /* @__PURE__ */ withParentHover(LayersIconSource);
export const LayoutDashboard = /* @__PURE__ */ withParentHover(
    LayoutDashboardIconSource,
);
export const LayoutGrid = /* @__PURE__ */ withParentHover(LayoutGridIconSource);
export const Lightbulb = /* @__PURE__ */ withParentHover(LightbulbIconSource);
export const List = /* @__PURE__ */ withParentHover(ListIconSource);
export const ListChecks = /* @__PURE__ */ withParentHover(ListChecksIconSource);
export const ListOrdered = /* @__PURE__ */ withParentHover(
    ListOrderedIconSource,
);
export const ListTodo = /* @__PURE__ */ withParentHover(ListChecksIconSource);
export const ListTree = /* @__PURE__ */ withParentHover(ListTreeIconSource);
export const Loader2Icon = /* @__PURE__ */ withParentHover(
    LoaderCircleIconSource,
);
export const LoaderCircle = /* @__PURE__ */ withParentHover(
    LoaderCircleIconSource,
);
export const Lock = /* @__PURE__ */ withParentHover(LockIconSource);
export const LockKeyhole = /* @__PURE__ */ withParentHover(LockIconSource);
export const LogOut = /* @__PURE__ */ withParentHover(LogOutIconSource);
export const Mail = /* @__PURE__ */ withParentHover(MailIconSource);
export const Menu = /* @__PURE__ */ withParentHover(MenuIconSource);
export const MessageSquare = /* @__PURE__ */ withParentHover(
    MessageSquareIconSource,
);
export const MessageSquareText = /* @__PURE__ */ withParentHover(
    MessageSquareTextIconSource,
);
export const MessagesSquare = /* @__PURE__ */ withParentHover(
    MessagesSquareIconSource,
);
export const Minus = /* @__PURE__ */ withParentHover(MinusIconSource);
export const Monitor = /* @__PURE__ */ withParentHover(MonitorIconSource);
export const Moon = /* @__PURE__ */ withParentHover(MoonIconSource);
export const MoreHorizontal =
    /* @__PURE__ */ withParentHover(EllipsisIconSource);
export const Package = /* @__PURE__ */ withParentHover(PackageIconSource);
export const PanelLeftClose = /* @__PURE__ */ withParentHover(
    PanelLeftCloseIconSource,
);
export const PanelLeftCloseIcon = /* @__PURE__ */ withParentHover(
    PanelLeftCloseIconSource,
);
export const PanelLeftOpen = /* @__PURE__ */ withParentHover(
    PanelLeftOpenIconSource,
);
export const PanelLeftOpenIcon = /* @__PURE__ */ withParentHover(
    PanelLeftOpenIconSource,
);
export const Pencil = /* @__PURE__ */ withParentHover(PencilIconSource);
export const Plug = /* @__PURE__ */ withParentHover(PlugIconSource);
export const Plus = /* @__PURE__ */ withParentHover(PlusIconSource);
export const RefreshCw = /* @__PURE__ */ withParentHover(RefreshCwIconSource);
export const Rocket = /* @__PURE__ */ withParentHover(RocketIconSource);
export const RotateCcw = /* @__PURE__ */ withParentHover(RotateCcwIconSource);
export const ScanLine = /* @__PURE__ */ withParentHover(ScanLineIconSource);
export const Search = /* @__PURE__ */ withParentHover(SearchIconSource);
export const SearchIcon = /* @__PURE__ */ withParentHover(SearchIconSource);
export const Settings = /* @__PURE__ */ withParentHover(SettingsIconSource);
export const ShieldAlert = /* @__PURE__ */ withParentHover(
    ShieldAlertIconSource,
);
export const ShieldCheck = /* @__PURE__ */ withParentHover(
    ShieldCheckIconSource,
);
export const ShieldQuestion = /* @__PURE__ */ withParentHover(
    ShieldAlertIconSource,
);
export const Sparkles = /* @__PURE__ */ withParentHover(SparklesIconSource);
export const Sun = /* @__PURE__ */ withParentHover(SunIconSource);
export const Trash2 = /* @__PURE__ */ withParentHover(Trash2IconSource);
export const Unplug = /* @__PURE__ */ withParentHover(PlugIconSource);
export const User = /* @__PURE__ */ withParentHover(UserIconSource);
export const Users = /* @__PURE__ */ withParentHover(UsersIconSource);
export const X = /* @__PURE__ */ withParentHover(XIconSource);
export const XIcon = /* @__PURE__ */ withParentHover(XIconSource);
export const Zap = /* @__PURE__ */ withParentHover(ZapIconSource);
