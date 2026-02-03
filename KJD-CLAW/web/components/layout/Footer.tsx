import Link from 'next/link';
import { Facebook, Instagram, Mail } from 'lucide-react';

export function Footer() {
  return (
    <footer className="bg-kjd-darkGreen text-kjd-lightBeige pt-16 pb-8">
      <div className="container mx-auto px-4">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-12 mb-12">
          {/* Brand */}
          <div className="col-span-1 md:col-span-1">
            <h3 className="text-2xl font-bold mb-4 tracking-tighter">
              KUBAJA<span className="text-kjd-goldBrown">DESIGNS</span>
            </h3>
            <p className="text-kjd-beige/60 text-sm leading-relaxed mb-6">
              Prémiové 3D tištěné doplňky a osvětlení. Design, který respektuje přírodu a moderní technologie.
            </p>
            <div className="flex space-x-4">
              <a href="#" className="text-kjd-beige hover:text-white transition-colors"><Instagram className="w-5 h-5" /></a>
              <a href="#" className="text-kjd-beige hover:text-white transition-colors"><Facebook className="w-5 h-5" /></a>
              <a href="#" className="text-kjd-beige hover:text-white transition-colors"><Mail className="w-5 h-5" /></a>
            </div>
          </div>

          {/* Links 1 */}
          <div>
            <h4 className="text-white font-bold mb-6 uppercase tracking-widest text-sm">Obchod</h4>
            <ul className="space-y-3 text-sm text-kjd-beige/80">
              <li><Link href="/kolekce" className="hover:text-kjd-goldBrown transition-colors">Všechny produkty</Link></li>
              <li><Link href="/kolekce/lampy" className="hover:text-kjd-goldBrown transition-colors">Lampy</Link></li>
              <li><Link href="/kolekce/vazy" className="hover:text-kjd-goldBrown transition-colors">Vázy</Link></li>
              <li><Link href="/konfigurator" className="hover:text-kjd-goldBrown transition-colors">Vlastní design</Link></li>
            </ul>
          </div>

          {/* Links 2 */}
          <div>
            <h4 className="text-white font-bold mb-6 uppercase tracking-widest text-sm">Informace</h4>
            <ul className="space-y-3 text-sm text-kjd-beige/80">
              <li><Link href="/o-nas" className="hover:text-kjd-goldBrown transition-colors">O značce</Link></li>
              <li><Link href="/doprava" className="hover:text-kjd-goldBrown transition-colors">Doprava a platba</Link></li>
              <li><Link href="/kontakty" className="hover:text-kjd-goldBrown transition-colors">Kontakty</Link></li>
              <li><Link href="/faq" className="hover:text-kjd-goldBrown transition-colors">Časté dotazy</Link></li>
            </ul>
          </div>

          {/* Newsletter */}
          <div>
            <h4 className="text-white font-bold mb-6 uppercase tracking-widest text-sm">Newsletter</h4>
            <p className="text-kjd-beige/60 text-sm mb-4">Odebírejte novinky a získejte slevu na první nákup.</p>
            <form className="flex">
              <input 
                type="email" 
                placeholder="Váš email" 
                className="bg-white/10 border-none text-white placeholder-white/40 px-4 py-2 rounded-l-md w-full focus:ring-1 focus:ring-kjd-goldBrown outline-none"
              />
              <button className="bg-kjd-goldBrown text-white px-4 py-2 rounded-r-md hover:bg-kjd-goldBrown/90 transition-colors">
                OK
              </button>
            </form>
          </div>
        </div>

        <div className="border-t border-white/10 pt-8 flex flex-col md:flex-row justify-between items-center text-xs text-kjd-beige/40">
          <p>&copy; {new Date().getFullYear()} Kubaja Designs. Všechna práva vyhrazena.</p>
          <div className="flex space-x-6 mt-4 md:mt-0">
            <Link href="/ochrana-udaju" className="hover:text-white">Ochrana údajů</Link>
            <Link href="/obchodni-podminky" className="hover:text-white">Obchodní podmínky</Link>
          </div>
        </div>
      </div>
    </footer>
  );
}
