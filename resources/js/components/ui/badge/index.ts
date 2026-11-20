import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Badge } from "./Badge.vue"

export const badgeVariants = cva(
  "inline-flex items-center justify-center rounded-full border px-2 py-0.5 text-caption font-medium tabular w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:ring-2 focus-visible:ring-ring transition-colors overflow-hidden",
  {
    variants: {
      variant: {
        default:
          "border-transparent bg-muted text-foreground [a&]:hover:bg-accent",
        secondary:
          "border-transparent bg-muted text-muted-foreground [a&]:hover:bg-accent",
        destructive:
         "border-transparent bg-destructive text-destructive-foreground [a&]:hover:bg-destructive/90",
        outline:
          "border-border-strong text-muted-foreground [a&]:hover:bg-accent",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
)
export type BadgeVariants = VariantProps<typeof badgeVariants>
