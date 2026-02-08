<?php

declare(strict_types=1);

namespace App\Infrastructure\DataFixtures;

use App\Domain\Model\MenuEntity;
use App\Domain\Model\ProductEntity;
use App\Domain\Model\SupplierEntity;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // 1. Create Suppliers and their Menus/Products
        $supplierNames = [
            'Gourmet Catering Co.',
            'Street Food Masters',
            'Healthy Bites Ltd.',
            'Asian Fusion Experts',
            'Classic European Delights'
        ];

        foreach ($supplierNames as $name) {
            $supplier = SupplierEntity::create($name);
            $manager->persist($supplier);

            // Generate between 5 and 10 menus for each supplier
            $menuCount = random_int(5, 10);
            for ($i = 1; $i <= $menuCount; $i++) {
                $title = sprintf('Seasonal Menu %d', $i);
                $price = (float) random_int(25, 75);
                
                // Create the Menu (Detailed Domain Model)
                $menu = MenuEntity::create(
                    title: $title,
                    price: $price,
                    currency: 'EUR',
                    supplier: $supplier,
                    description: 'A delicious selection of seasonal dishes crafted by our expert chefs.'
                );
                $manager->persist($menu);

                // Create the Product (Generic Catalog Model) linked via UUID
                $product = ProductEntity::create(
                    name: sprintf('%s - %s', $name, $title),
                    price: $price,
                    supplier: $supplier,
                    type: ProductEntity::TYPE_MENU,
                    externalReferenceId: $menu->id
                );
                $manager->persist($product);
            }
        }

        $manager->flush();
    }
}
