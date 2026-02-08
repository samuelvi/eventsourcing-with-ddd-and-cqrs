<?php

declare(strict_types=1);

namespace App\Infrastructure\Factory;

use App\Domain\Model\MenuEntity;
use App\Domain\Model\ProductEntity;
use App\Domain\Model\SupplierEntity;
use App\Domain\Repository\MenuWriteRepositoryInterface;
use App\Domain\Service\ProductDetailFactoryInterface;
use App\Infrastructure\Persistence\Doctrine\ReadEntityManager;
use Symfony\Component\Uid\Uuid;

final readonly class MenuProductFactory implements ProductDetailFactoryInterface
{
    public function __construct(
        private MenuWriteRepositoryInterface $menuRepository,
        private ReadEntityManager $readEntityManager,
    ) {}

    public function supports(string $type): bool
    {
        return $type === ProductEntity::TYPE_MENU;
    }

    public function create(array $data, SupplierEntity $supplier): Uuid
    {
        // En un escenario real, validaríamos $data aquí o usaríamos un DTO específico.
        $menu = MenuEntity::create(
            title: $data['title'] ?? 'Untitled Menu',
            price: (float) ($data['price'] ?? 0.0),
            currency: $data['currency'] ?? 'EUR',
            supplier: $supplier,
            description: $data['description'] ?? null
        );

        $this->menuRepository->save($menu);

        return $menu->getId();
    }

    public function getDetails(Uuid $referenceId): ?object
    {
        // Usamos ReadEntityManager (DBAL) para obtener los datos crudos y rápidos
        // O podríamos hidratar la entidad si la necesitamos.
        // Para API Platform, devolver la entidad es útil para que se serialice automáticamente.
        
        // OPCIÓN RÁPIDA (DBAL): Devolver array.
        // OPCIÓN ROBUSTA (ORM): Devolver entidad.
        // Dado que hemos definido API Resources, vamos a devolver la entidad.
        // Pero como ReadEntityManager es DBAL, vamos a hacer un truco rápido o inyectar el repositorio de lectura.
        
        $sql = 'SELECT * FROM menus WHERE id = :id';
        $data = $this->readEntityManager->fetchOne($sql, ['id' => $referenceId->toRfc4122()]);

        if (!$data) {
            return null;
        }

        // Hidratación manual parcial para visualización (DTO style)
        // Nota: Esto no devuelve una entidad gestionada por Doctrine, lo cual es perfecto para lectura.
        // Sin embargo, para que API Platform lo serialice usando los grupos de MenuEntity, 
        // necesitamos que sea una instancia de MenuEntity o un DTO mapeado.
        
        // Usamos reflection para recrear el objeto sin constructor si fuera necesario, 
        // o usamos el constructor si tenemos los datos.
        // MenuEntity tiene un constructor protegido que pide SupplierEntity.
        // Aquí solo tenemos supplier_id.
        // Para simplificar esta POC, devolveremos un objeto anónimo o array, 
        // pero lo ideal sería un MenuDto.
        
        // Vamos a devolver los datos crudos y dejar que el Provider principal lo maneje,
        // o mejor, instanciamos MenuEntity::create() si tuviéramos el supplier cargado.
        
        return (object) $data; 
    }
}
