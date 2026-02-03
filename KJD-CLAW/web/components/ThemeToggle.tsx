"use client"

import * as React from "react"
import { Moon, Sun, Lightbulb } from "lucide-react"
import { useTheme } from "next-themes"
import { motion } from "framer-motion"

export function ThemeToggle() {
  const { setTheme, theme } = useTheme()
  const [mounted, setMounted] = React.useState(false)

  React.useEffect(() => {
    setMounted(true)
  }, [])

  if (!mounted) return null

  const isDark = theme === 'dark'

  return (
    <button
      onClick={() => setTheme(isDark ? "light" : "dark")}
      className="relative flex items-center gap-2 px-4 py-2 rounded-full border border-kjd-earthGreen/30 bg-white/5 backdrop-blur-sm hover:bg-white/20 transition-all group"
    >
      <span className="text-sm font-medium hidden sm:inline-block text-kjd-darkGreen dark:text-kjd-lightBeige">
        {isDark ? "Lights ON" : "Lights OFF"}
      </span>
      <div className="relative w-6 h-6 flex items-center justify-center">
        <motion.div
          initial={false}
          animate={{ rotate: isDark ? 180 : 0, scale: isDark ? 0 : 1 }}
          transition={{ duration: 0.3 }}
          className="absolute"
        >
          <Sun className="w-5 h-5 text-kjd-goldBrown" />
        </motion.div>
        <motion.div
            initial={false}
            animate={{ rotate: isDark ? 0 : -180, scale: isDark ? 1 : 0 }}
            transition={{ duration: 0.3 }}
            className="absolute"
          >
            <Lightbulb className="w-5 h-5 text-yellow-400 fill-yellow-400 drop-shadow-[0_0_10px_rgba(250,204,21,0.8)]" />
          </motion.div>
      </div>
    </button>
  )
}
