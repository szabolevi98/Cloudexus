<?php

namespace Cloudexus\Model\Core;

use Cloudexus\Core\DatabaseConnection;

class PartnerAddressModel
{
    public function forPartner(int $partnerId): array
    {
        $stmt = DatabaseConnection::get()->prepare(
            'SELECT * FROM partner_addresses WHERE partner_id = :id ORDER BY id ASC'
        );
        $stmt->execute(['id' => $partnerId]);
        return $stmt->fetchAll();
    }

    /** All addresses across all partners, for the order form's client-side partner→address filtering. */
    public function all(): array
    {
        return DatabaseConnection::get()
            ->query('SELECT * FROM partner_addresses ORDER BY partner_id ASC, id ASC')
            ->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = DatabaseConnection::get()->prepare('SELECT * FROM partner_addresses WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = DatabaseConnection::get()->prepare(
            'INSERT INTO partner_addresses (partner_id, country, city, postal_code, street, note, created_at)
             VALUES (:partner_id, :country, :city, :postal_code, :street, :note, NOW())'
        );
        $stmt->execute([
            'partner_id' => $data['partner_id'],
            'country' => $data['country'] ?: 'Magyarország',
            'city' => $data['city'],
            'postal_code' => $data['postal_code'],
            'street' => $data['street'],
            'note' => $data['note'] ?: null,
        ]);
        return (int) DatabaseConnection::get()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        DatabaseConnection::get()->prepare(
            'UPDATE partner_addresses SET country = :country, city = :city, postal_code = :postal_code, street = :street, note = :note
             WHERE id = :id'
        )->execute([
            'id' => $id,
            'country' => $data['country'] ?: 'Magyarország',
            'city' => $data['city'],
            'postal_code' => $data['postal_code'],
            'street' => $data['street'],
            'note' => $data['note'] ?: null,
        ]);
    }

    public function delete(int $id): void
    {
        DatabaseConnection::get()->prepare('DELETE FROM partner_addresses WHERE id = :id')->execute(['id' => $id]);
    }

    /** One-line formatted address, e.g. "Magyarország, 1111 Budapest, Példa utca 1. (2. emelet 3. ajtó)". */
    public static function format(array $address): string
    {
        $label = trim($address['postal_code'] . ' ' . $address['city']) . ', ' . $address['street'];
        if (!empty($address['country']) && $address['country'] !== 'Magyarország') {
            $label = $address['country'] . ', ' . $label;
        }
        if (!empty($address['note'])) {
            $label .= ' (' . $address['note'] . ')';
        }
        return $label;
    }
}
