import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Button } from "./Button.vue"

/**
 * Button variants (rule B1): `primary` is the one filled violet action per
 * surface (B2), `secondary` the white outline, `ghost` for tertiary and icon
 * buttons, `link` for text links, and `destructive` only inside
 * ConfirmDeleteDialog (F5). Disabled keeps its variant at 50% (B5).
 */
export const buttonVariants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-body font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 aria-invalid:border-destructive",
  {
    variants: {
      variant: {
        primary:
          "bg-primary text-primary-foreground enabled:hover:bg-primary-hover [a&]:hover:bg-primary-hover",
        secondary:
          "border border-border-strong bg-secondary text-secondary-foreground enabled:hover:bg-accent [a&]:hover:bg-accent",
        ghost:
          "text-foreground enabled:hover:bg-accent [a&]:hover:bg-accent",
        link: "h-auto px-0 text-foreground underline decoration-border-strong underline-offset-4 enabled:hover:decoration-current [a&]:hover:decoration-current",
        destructive:
          "bg-destructive text-destructive-foreground enabled:hover:bg-destructive/90 [a&]:hover:bg-destructive/90 focus-visible:ring-destructive",
      },
      size: {
        "default": "h-9 px-4 has-[>svg]:px-3",
        "sm": "h-8 gap-1.5 px-3 has-[>svg]:px-2.5",
        "icon": "size-9",
        "icon-sm": "size-8",
      },
    },
    defaultVariants: {
      variant: "primary",
      size: "default",
    },
  },
)
export type ButtonVariants = VariantProps<typeof buttonVariants>
