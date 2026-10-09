import * as React from "react"
import { cn } from "@/lib/utils"

function Textarea({ className, ...props }: React.ComponentProps<"textarea">) {
  return (
    <textarea
      data-slot="textarea"
      className={cn(
        "h-10 w-full min-w-0 rounded-sm border border-input bg-card px-3 text-sm text-foreground transition-[border-color] duration-150 pointer-coarse:text-base placeholder:text-muted-foreground hover:border-muted-foreground disabled:cursor-not-allowed disabled:opacity-60",
        "outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring",
        "flex field-sizing-content h-auto min-h-24 py-2.5 aria-invalid:border-destructive",
        className
      )}
      {...props}
    />
  )
}

export { Textarea }
