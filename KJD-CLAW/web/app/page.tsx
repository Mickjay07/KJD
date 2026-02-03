import Image from "next/image";
import Link from "next/link";
import { ArrowRight, Leaf, Zap, ShieldCheck } from "lucide-react";

export default function Home() {
  return (
    <div className="flex flex-col gap-20 pb-20">
      
      {/* Hero Section */}
      <section className="relative h-[85vh] w-full overflow-hidden flex items-center justify-center">
        {/* Background - using one of the collection images as bg for now, dimmed */}
        <div className="absolute inset-0 z-0">
          <Image
            src="/images/collections/lampy.webp"
            alt="Hero Background"
            fill
            className="object-cover opacity-80 dark:opacity-40"
            priority
          />
          <div className="absolute inset-0 bg-gradient-to-t from-kjd-lightBeige via-transparent to-transparent dark:from-kjd-darkGreen dark:via-kjd-darkGreen/50 dark:to-black/30" />
        </div>

        <div className="relative z-10 container mx-auto px-4 text-center">
          <h1 className="text-5xl md:text-7xl lg:text-8xl font-bold tracking-tighter mb-6 text-kjd-darkGreen dark:text-white drop-shadow-sm">
            Tvar světla.
            <br />
            <span className="text-kjd-goldBrown italic font-light">Dotek přírody.</span>
          </h1>
          <p className="text-xl md:text-2xl mb-10 text-kjd-darkGreen/80 dark:text-kjd-lightBeige/90 max-w-2xl mx-auto font-light">
            Prémiové 3D tištěné doplňky, které promění váš domov. 
            Udržitelné materiály, precizní design.
          </p>
          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            <Link 
              href="/kolekce" 
              className="bg-kjd-darkGreen dark:bg-kjd-beige text-white dark:text-kjd-darkGreen px-8 py-4 rounded-full font-medium hover:scale-105 transition-transform flex items-center justify-center gap-2"
            >
              Prohlédnout kolekce <ArrowRight className="w-4 h-4" />
            </Link>
            <Link 
              href="/konfigurator" 
              className="bg-white/20 backdrop-blur-md border border-kjd-darkGreen/20 dark:border-white/20 text-kjd-darkGreen dark:text-white px-8 py-4 rounded-full font-medium hover:bg-white/30 transition-all"
            >
              Vlastní design
            </Link>
          </div>
        </div>
      </section>

      {/* Values Section */}
      <section className="container mx-auto px-4">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
          {[
            {
              icon: <Leaf className="w-8 h-8 text-kjd-earthGreen" />,
              title: "Ekologické materiály",
              desc: "Tiskneme z bioplastů na bázi kukuřičného škrobu. 100% rozložitelné, šetrné k přírodě."
            },
            {
              icon: <Zap className="w-8 h-8 text-kjd-goldBrown" />,
              title: "Precizní technologie",
              desc: "Využíváme nejmodernější FDM tiskárny pro dokonalý detail a vrstvenou texturu."
            },
            {
              icon: <ShieldCheck className="w-8 h-8 text-kjd-beige" />,
              title: "Ruční kompletace",
              desc: "Každý kus prochází pečlivou kontrolou a ruční kompletací v naší dílně."
            }
          ].map((item, i) => (
            <div key={i} className="bg-white dark:bg-white/5 p-8 rounded-2xl shadow-sm border border-kjd-darkGreen/5 dark:border-white/5 hover:border-kjd-goldBrown/30 transition-colors">
              <div className="mb-4">{item.icon}</div>
              <h3 className="text-xl font-bold mb-2">{item.title}</h3>
              <p className="text-kjd-darkGreen/70 dark:text-kjd-lightBeige/60 leading-relaxed">{item.desc}</p>
            </div>
          ))}
        </div>
      </section>

      {/* Categories Grid */}
      <section className="container mx-auto px-4">
        <div className="flex justify-between items-end mb-12">
          <div>
            <h2 className="text-3xl md:text-5xl font-bold tracking-tighter mb-4">Naše kolekce</h2>
            <p className="text-kjd-darkGreen/60 dark:text-kjd-lightBeige/60">Vyberte si to pravé pro váš interiér.</p>
          </div>
          <Link href="/kolekce" className="hidden md:flex items-center gap-2 text-kjd-goldBrown hover:translate-x-1 transition-transform">
            Všechny produkty <ArrowRight className="w-4 h-4" />
          </Link>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 h-auto md:h-[600px]">
          {[
            { title: "Designové Lampy", img: "/images/collections/lampy.webp", href: "/kolekce/lampy", size: "lg:col-span-2 lg:row-span-2" },
            { title: "Vázy & Dekorace", img: "/images/collections/vazy.webp", href: "/kolekce/vazy", size: "" },
            { title: "Květináče", img: "/images/collections/kvetinace.webp", href: "/kolekce/kvetinace", size: "" },
            { title: "Jarní edice", img: "/images/collections/jarni.webp", href: "/kolekce/jarni", size: "lg:col-span-2" },
          ].map((cat, i) => (
            <Link 
              key={i} 
              href={cat.href}
              className={`group relative overflow-hidden rounded-2xl ${cat.size} min-h-[300px]`}
            >
              <Image
                src={cat.img}
                alt={cat.title}
                fill
                className="object-cover group-hover:scale-105 transition-transform duration-700"
              />
              <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent opacity-80 group-hover:opacity-90 transition-opacity" />
              <div className="absolute bottom-0 left-0 p-8">
                <h3 className="text-2xl text-white font-bold mb-2 translate-y-2 group-hover:translate-y-0 transition-transform">{cat.title}</h3>
                <span className="text-kjd-beige text-sm opacity-0 group-hover:opacity-100 transition-opacity delay-100 flex items-center gap-2">
                  Prozkoumat <ArrowRight className="w-3 h-3" />
                </span>
              </div>
            </Link>
          ))}
        </div>
      </section>

      {/* CTA / Newsletter Teaser */}
      <section className="container mx-auto px-4 my-20">
        <div className="bg-kjd-goldBrown/10 dark:bg-white/5 rounded-3xl p-12 md:p-20 text-center relative overflow-hidden">
          <div className="relative z-10 max-w-2xl mx-auto">
            <h2 className="text-3xl md:text-5xl font-bold mb-6 tracking-tighter">Máte vlastní představu?</h2>
            <p className="text-lg mb-8 opacity-80">
              Kromě stálých kolekcí nabízíme i zakázkovou výrobu. 
              Vytvořte si vlastní lithophane lampu s vaší fotografií nebo nám napište o unikátní design.
            </p>
            <Link 
              href="/konfigurator"
              className="inline-block bg-kjd-darkGreen text-white px-8 py-4 rounded-full font-bold hover:bg-black transition-colors"
            >
              Vyzkoušet konfigurátor
            </Link>
          </div>
          {/* Decorative circles */}
          <div className="absolute top-0 left-0 w-64 h-64 bg-kjd-goldBrown/20 rounded-full blur-3xl -translate-x-1/2 -translate-y-1/2" />
          <div className="absolute bottom-0 right-0 w-64 h-64 bg-kjd-earthGreen/20 rounded-full blur-3xl translate-x-1/2 translate-y-1/2" />
        </div>
      </section>
    </div>
  );
}
