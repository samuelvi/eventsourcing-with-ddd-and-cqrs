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
    private array $countries = [
        'ES',
        'FR',
        'IT',
        'DE',
        'PT',
        'US',
        'GB',
        'NL',
        'BE',
        'AT',
        'CH'
    ];

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
            $supplier->country = $this->countries[array_rand($this->countries)];
            $manager->persist($supplier);

            // Generate between 5 and 10 menus for each supplier
            $menuCount = random_int(5, 10);
            for ($i = 1; $i <= $menuCount; $i++) {
                $title = sprintf('Seasonal Menu %d', $i);
                $price = (float) random_int(25, 75);
                
                // 1. Create the Product (Commercial Truth)
                $productId = \Symfony\Component\Uid\Uuid::v7();
                $product = ProductEntity::hydrate(
                    id: $productId,
                    name: sprintf('%s - %s', $name, $title),
                    price: $price,
                    currency: 'EUR',
                    type: ProductEntity::TYPE_MENU,
                    supplier: $supplier,
                    externalReferenceId: $productId
                );
                $manager->persist($product);

                // 2. Create the Menu (Technical Details)
                $menu = MenuEntity::hydrate(
                    id: $productId,
                    title: $title,
                    description: 'A delicious selection of seasonal dishes crafted by our expert chefs.'
                );
                $manager->persist($menu);
            }
        }

        // 2. Create inactive supplier (for testing derivation rules)
        $inactiveSupplier = SupplierEntity::create('Inactive Supplier Co.');
        $inactiveSupplier->country = 'ES';
        $inactiveSupplier->isActive = false;
        $manager->persist($inactiveSupplier);

        // Add some menus for inactive supplier
        for ($i = 1; $i <= 3; $i++) {
            $title = sprintf('Menu 4 All %d', $i);
            $price = (float) random_int(25, 75);
            
            $productId = \Symfony\Component\Uid\Uuid::v7();
            $product = ProductEntity::hydrate(
                id: $productId,
                name: sprintf('Inactive Supplier Co. - %s', $title),
                price: $price,
                currency: 'EUR',
                type: ProductEntity::TYPE_MENU,
                supplier: $inactiveSupplier,
                externalReferenceId: $productId
            );
            $manager->persist($product);

            $menu = MenuEntity::hydrate(
                id: $productId,
                title: $title,
                description: 'A special menu from inactive supplier.'
            );
            $manager->persist($menu);
        }

        $manager->flush();
    }
}
