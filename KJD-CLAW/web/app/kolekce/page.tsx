import Image from 'next/image';
import Link from 'next/link';
import { prisma } from '@/lib/prisma';
import { ArrowRight } from 'lucide-react';

export default async function CollectionsPage() {
  const categories = await prisma.category.findMany({
    include: {
      products: {
        take: 4,
      },
    },
  });

  return (
    <div className="container mx-auto px-4 py-12">
      <h1 className="text-4xl md:text-6xl font-bold mb-4 tracking-tighter text-kjd-darkGreen dark:text-white">
        Kolekce
      </h1>
      <p className="text-lg text-kjd-darkGreen/60 dark:text-kjd-lightBeige/60 mb-16 max-w-2xl">
        Prozkoumejte naše produktové řady. Každý kus je originál vytištěný s důrazem na detail a udržitelnost.
      </p>

      <div className="space-y-32">
        {categories.map((category, index) => (
          <section key={category.id} className="group">
            <div className="flex flex-col md:flex-row justify-between items-end mb-8 border-b border-kjd-darkGreen/10 dark:border-white/10 pb-4">
              <div>
                <h2 className="text-3xl font-bold mb-2 flex items-center gap-4">
                  <span className="text-kjd-goldBrown opacity-40">0{index + 1}</span>
                  {category.name}
                </h2>
              </div>
              <Link 
                href={`/kolekce/${category.slug}`}
                className="hidden md:flex items-center gap-2 text-sm font-bold uppercase tracking-widest hover:text-kjd-goldBrown transition-colors"
              >
                Zobrazit vše <ArrowRight className="w-4 h-4" />
              </Link>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
              {/* Category Highlight Card */}
              <Link href={`/kolekce/${category.slug}`} className="lg:col-span-2 relative aspect-[4/3] rounded-2xl overflow-hidden group/card cursor-pointer">
                <Image
                  src={category.image || '/images/placeholder.webp'}
                  alt={category.name}
                  fill
                  className="object-cover transition-transform duration-700 group-hover/card:scale-105"
                />
                <div className="absolute inset-0 bg-black/20 group-hover/card:bg-black/10 transition-colors" />
                <div className="absolute bottom-8 left-8 text-white">
                  <h3 className="text-2xl font-bold mb-2">Prohlédnout kolekci</h3>
                  <span className="inline-block bg-white/20 backdrop-blur-md px-4 py-2 rounded-full text-sm">
                    {category.products.length} produktů
                  </span>
                </div>
              </Link>

              {/* Product Cards */}
              {category.products.map((product) => (
                <Link key={product.id} href={`/produkt/${product.slug}`} className="block group/product">
                  <div className="relative aspect-square rounded-2xl overflow-hidden bg-kjd-darkGreen/5 dark:bg-white/5 mb-4">
                    {product.image && (
                      <Image
                        src={product.image}
                        alt={product.name}
                        fill
                        className="object-cover transition-transform duration-500 group-hover/product:scale-110"
                      />
                    )}
                    {/* Add to cart overlay placeholder */}
                    <div className="absolute inset-0 bg-black/0 group-hover/product:bg-black/20 transition-colors flex items-center justify-center opacity-0 group-hover/product:opacity-100">
                      <span className="bg-white text-kjd-darkGreen px-6 py-3 rounded-full font-bold shadow-lg transform translate-y-4 group-hover/product:translate-y-0 transition-transform">
                        Detail
                      </span>
                    </div>
                  </div>
                  <h3 className="font-bold text-lg mb-1 group-hover/product:text-kjd-goldBrown transition-colors">{product.name}</h3>
                  <p className="text-kjd-darkGreen/60 dark:text-kjd-lightBeige/60">{product.price} Kč</p>
                </Link>
              ))}
            </div>
          </section>
        ))}
      </div>
    </div>
  );
}
