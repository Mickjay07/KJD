import { prisma } from '@/lib/prisma';
import Image from 'next/image';
import Link from 'next/link';
import { notFound } from 'next/navigation';

export default async function CategoryPage({ params }: { params: { slug: string } }) {
  const category = await prisma.category.findUnique({
    where: { slug: params.slug },
    include: { products: true },
  });

  if (!category) {
    notFound();
  }

  return (
    <div className="container mx-auto px-4 py-12">
      <div className="mb-12 text-center">
        <h1 className="text-4xl md:text-6xl font-bold mb-4 capitalize">{category.name}</h1>
        <p className="text-xl opacity-60">Jedinečné kousky z naší dílny</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
        {category.products.map((product) => (
          <Link key={product.id} href={`/produkt/${product.slug}`} className="group">
            <div className="relative aspect-square rounded-2xl overflow-hidden bg-kjd-darkGreen/5 dark:bg-white/5 mb-4">
              {product.image && (
                <Image
                  src={product.image}
                  alt={product.name}
                  fill
                  className="object-cover transition-transform duration-500 group-hover:scale-110"
                />
              )}
            </div>
            <h3 className="font-bold text-lg mb-1 group-hover:text-kjd-goldBrown transition-colors">{product.name}</h3>
            <p className="opacity-60">{product.price} Kč</p>
          </Link>
        ))}
      </div>
    </div>
  );
}
