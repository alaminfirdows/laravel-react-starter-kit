import * as React from "react"

import { cn } from "@/lib/utils"

function Input({ className, type, ...props }: React.ComponentProps<"input">) {
  return (
    <input
      type={type}
      data-slot="input"
      className={cn(
        "h-10 w-full min-w-0 rounded-sm border border-input bg-card px-3 text-sm text-foreground transition-[border-color] duration-150 pointer-coarse:text-base placeholder:text-muted-foreground hover:border-muted-foreground disabled:cursor-not-allowed disabled:opacity-60",
        "outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring",
        "file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-semibold file:text-foreground aria-invalid:border-destructive",
        className
      )}
      {...props}
    />
  )
}

export { Input }
