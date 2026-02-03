const { PrismaClient } = require('@prisma/client');
const prisma = new PrismaClient();

async function main() {
  // Categories
  const catLamps = await prisma.category.create({
    data: { name: 'Lampy', slug: 'lampy', image: '/images/collections/lampy.webp' }
  });
  const catVases = await prisma.category.create({
    data: { name: 'Vázy', slug: 'vazy', image: '/images/collections/vazy.webp' }
  });
  const catPots = await prisma.category.create({
    data: { name: 'Květináče', slug: 'kvetinace', image: '/images/collections/kvetinace.webp' }
  });

  // Products
  await prisma.product.create({
    data: {
      name: 'Forest Mushroom Lamp',
      slug: 'forest-mushroom-lamp',
      description: 'Lampa inspirovaná tvarem lesních hub. Jemné rozptýlené světlo.',
      price: 1290,
      image: '/images/collections/lampy.webp',
      categoryId: catLamps.id
    }
  });

  await prisma.product.create({
    data: {
      name: 'Minimalist Tube',
      slug: 'minimalist-tube',
      description: 'Jednoduchá, elegantní stolní lampa.',
      price: 890,
      image: '/images/collections/lampy.webp',
      categoryId: catLamps.id
    }
  });

  await prisma.product.create({
    data: {
      name: 'Spiral Vase',
      slug: 'spiral-vase',
      description: 'Váza se spirálovitou strukturou.',
      price: 590,
      image: '/images/collections/vazy.webp',
      categoryId: catVases.id
    }
  });

  console.log('Seed finished.');
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
