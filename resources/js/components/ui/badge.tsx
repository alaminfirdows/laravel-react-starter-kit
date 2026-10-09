import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

// Quiet chips ported from product-repo-ui. Status badges keep their word;
// colour only reinforces it.
const badgeVariants = cva(
  "inline-flex min-h-6 w-fit shrink-0 items-center gap-1 rounded-[4px] border border-transparent px-2 font-mono text-xs whitespace-nowrap [&_svg]:pointer-events-none [&_svg]:size-3 [&_svg]:shrink-0",
  {
    variants: {
      variant: {
        default: "bg-foreground text-background",
        secondary: "bg-muted text-ink-2",
        outline: "border-border-strong bg-card text-foreground",
        muted: "bg-muted text-ink-2",
        destructive: "bg-destructive-tint text-destructive",
        primary: "bg-brand-tint text-primary",
        success: "bg-success-tint text-success",
        warning: "bg-warning-tint text-warning",
        info: "bg-info/12 text-info dark:bg-info/18",
        danger: "bg-destructive-tint text-destructive",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
)

function Badge({
  className,
  variant,
  asChild = false,
  ...props
}: React.ComponentProps<"span"> &
  VariantProps<typeof badgeVariants> & { asChild?: boolean }) {
  const Comp = asChild ? Slot : "span"

  return (
    <Comp
      data-slot="badge"
      className={cn(badgeVariants({ variant }), className)}
      {...props}
    />
  )
}

export { Badge, badgeVariants }
