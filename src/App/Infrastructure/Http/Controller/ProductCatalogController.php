<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Domain\Repository\ProductReadRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ProductCatalogController extends AbstractController
{
    public function __construct(
        private ProductReadRepositoryInterface $productRepository
    ) {}

    #[Route('/api/products-catalog', name: 'api_products_catalog', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $data = $this->productRepository->findCatalog();

        return new JsonResponse([
            '@context' => '/api/contexts/ProductCatalog',
            '@id' => '/api/products-catalog',
            '@type' => 'hydra:Collection',
            'hydra:member' => $data,
            'hydra:totalItems' => count($data),
        ]);
    }
}
