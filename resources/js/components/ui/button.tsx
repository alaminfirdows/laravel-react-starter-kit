import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

// Ported from product-repo-ui (src/components/ui/button.tsx, v2 look).
// `default` is the one orange button per view; `ink` is the neutral solid.
const buttonVariants = cva(
  [
    "group/button inline-flex shrink-0 cursor-pointer items-center justify-center gap-2 rounded-sm border border-transparent bg-clip-padding text-control whitespace-nowrap select-none",
    "transition-[background-color,border-color,color,box-shadow,transform] duration-150 ease-[cubic-bezier(0.16,1,0.3,1)] motion-safe:active:not-aria-[haspopup]:translate-y-px",
    "outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring",
    "disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50",
    "aria-invalid:border-destructive",
    "[&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
  ],
  {
    variants: {
      variant: {
        default:
          "border-brand-press bg-brand brand-fill text-primary-foreground hover:bg-brand-hover active:bg-brand-press",
        ink: "bg-foreground text-background hover:bg-foreground/85",
        outline:
          "border-border-strong bg-card text-foreground elev-1 hover:border-input hover:bg-muted aria-expanded:border-input aria-expanded:bg-muted",
        secondary:
          "border-border bg-muted text-foreground hover:border-border-strong aria-expanded:border-border-strong",
        ghost:
          "bg-transparent text-ink-2 hover:bg-muted hover:text-foreground aria-expanded:bg-muted aria-expanded:text-foreground",
        destructive:
          "bg-destructive-tint text-destructive hover:border-destructive/40",
        link: "text-foreground underline-offset-4 hover:underline",
      },
      size: {
        default:
          "h-10 px-4 has-data-[icon=inline-end]:pr-3 has-data-[icon=inline-start]:pl-3",
        xs: "h-7 gap-1 px-2.5 text-[0.8125rem] has-data-[icon=inline-end]:pr-2 has-data-[icon=inline-start]:pl-2 [&_svg:not([class*='size-'])]:size-3.5",
        sm: "h-8 gap-1.5 px-3 has-data-[icon=inline-end]:pr-2.5 has-data-[icon=inline-start]:pl-2.5",
        lg: "h-12 px-5 has-data-[icon=inline-end]:pr-4 has-data-[icon=inline-start]:pl-4",
        icon: "size-10",
        "icon-sm": "size-8",
        "icon-xs": "size-7 [&_svg:not([class*='size-'])]:size-3.5",
        "icon-lg": "size-12",
      },
    },
    compoundVariants: [
      // A link reads as text: no box, whatever size it's given.
      { variant: "link", className: "h-auto p-0" },
    ],
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  }
)

function Button({
  className,
  variant,
  size,
  asChild = false,
  ...props
}: React.ComponentProps<"button"> &
  VariantProps<typeof buttonVariants> & {
    asChild?: boolean
  }) {
  const Comp = asChild ? Slot : "button"

  return (
    <Comp
      data-slot="button"
      className={cn(buttonVariants({ variant, size, className }))}
      {...props}
    />
  )
}

export { Button, buttonVariants }
