import * as CheckboxPrimitive from "@radix-ui/react-checkbox"
import { CheckIcon } from "lucide-react"
import * as React from "react"

import { cn } from "@/lib/utils"

function Checkbox({
  className,
  ...props
}: React.ComponentProps<typeof CheckboxPrimitive.Root>) {
  return (
    <CheckboxPrimitive.Root
      data-slot="checkbox"
      className={cn(
        "relative pointer-coarse:after:absolute pointer-coarse:after:top-1/2 pointer-coarse:after:left-1/2 pointer-coarse:after:size-full pointer-coarse:after:min-h-11 pointer-coarse:after:min-w-11 pointer-coarse:after:-translate-x-1/2 pointer-coarse:after:-translate-y-1/2",
        "peer flex size-4 shrink-0 cursor-pointer items-center justify-center rounded-[4px] border border-input bg-card transition-[background-color,border-color] duration-150 ease-[cubic-bezier(0.16,1,0.3,1)]",
        "outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring",
        "group-has-disabled/field:opacity-50 hover:border-muted-foreground aria-invalid:border-destructive data-[state=checked]:border-foreground data-[state=checked]:bg-foreground data-[state=checked]:text-background aria-invalid:data-[state=checked]:border-foreground disabled:cursor-not-allowed disabled:opacity-60",
        className
      )}
      {...props}
    >
      <CheckboxPrimitive.Indicator
        data-slot="checkbox-indicator"
        className="grid place-content-center text-current transition-none [&>svg]:size-3.5"
      >
        <CheckIcon />
      </CheckboxPrimitive.Indicator>
    </CheckboxPrimitive.Root>
  )
}

export { Checkbox }
