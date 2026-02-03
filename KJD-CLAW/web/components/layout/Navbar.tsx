'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';
import { Menu, Search, ShoppingBag, User, X } from 'lucide-react';
import { ThemeToggle } from '@/components/ThemeToggle';
import { cn } from '@/lib/utils';

const navLinks = [
  { href: '/', label: 'Domů' },
  { href: '/kolekce', label: 'Kolekce' },
  { href: '/konfigurator', label: 'Konfigurátor' },
  { href: '/o-nas', label: 'O nás' },
];

export function Navbar() {
  const [isScrolled, setIsScrolled] = useState(false);
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

  useEffect(() => {
    const handleScroll = () => {
      setIsScrolled(window.scrollY > 20);
    };
    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  return (
    <nav
      className={cn(
        'fixed top-0 left-0 right-0 z-50 transition-all duration-300',
        isScrolled
          ? 'bg-white/80 dark:bg-kjd-darkGreen/80 backdrop-blur-md shadow-sm py-4'
          : 'bg-transparent py-6'
      )}
    >
      <div className="container mx-auto px-4 flex items-center justify-between">
        {/* Logo */}
        <Link href="/" className="text-2xl font-bold tracking-tighter text-kjd-darkGreen dark:text-kjd-beige">
          KUBAJA<span className="text-kjd-goldBrown">DESIGNS</span>
        </Link>

        {/* Desktop Links */}
        <div className="hidden md:flex items-center space-x-8">
          {navLinks.map((link) => (
            <Link
              key={link.href}
              href={link.href}
              className="text-sm font-medium text-kjd-darkGreen/80 dark:text-kjd-lightBeige/80 hover:text-kjd-goldBrown dark:hover:text-white transition-colors uppercase tracking-widest"
            >
              {link.label}
            </Link>
          ))}
        </div>

        {/* Actions */}
        <div className="flex items-center space-x-4">
          <button className="p-2 text-kjd-darkGreen dark:text-kjd-lightBeige hover:bg-black/5 dark:hover:bg-white/10 rounded-full transition-colors">
            <Search className="w-5 h-5" />
          </button>
          <button className="p-2 text-kjd-darkGreen dark:text-kjd-lightBeige hover:bg-black/5 dark:hover:bg-white/10 rounded-full transition-colors relative">
            <ShoppingBag className="w-5 h-5" />
            <span className="absolute top-0 right-0 w-4 h-4 bg-kjd-goldBrown text-white text-[10px] flex items-center justify-center rounded-full">0</span>
          </button>
          <div className="hidden md:block">
            <ThemeToggle />
          </div>
          
          {/* Mobile Menu Button */}
          <button 
            className="md:hidden p-2 text-kjd-darkGreen dark:text-kjd-lightBeige"
            onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
          >
            {isMobileMenuOpen ? <X /> : <Menu />}
          </button>
        </div>
      </div>

      {/* Mobile Menu Overlay */}
      {isMobileMenuOpen && (
        <div className="absolute top-full left-0 right-0 bg-white dark:bg-kjd-darkGreen border-t border-black/5 dark:border-white/10 p-4 md:hidden flex flex-col space-y-4 shadow-xl">
          {navLinks.map((link) => (
            <Link
              key={link.href}
              href={link.href}
              className="text-lg font-medium text-kjd-darkGreen dark:text-kjd-lightBeige py-2"
              onClick={() => setIsMobileMenuOpen(false)}
            >
              {link.label}
            </Link>
          ))}
        </div>
      )}
    </nav>
  );
}
