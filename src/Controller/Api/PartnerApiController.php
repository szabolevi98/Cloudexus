<?php

namespace Cloudexus\Controller\Api;

use Cloudexus\Model\Core\PartnerAddressModel;
use Cloudexus\Model\Core\PartnerModel;

class PartnerApiController extends ApiController
{
    private const TYPES = ['customer', 'supplier', 'both'];

    private function partners(): PartnerModel
    {
        return new PartnerModel();
    }

    private function addresses(): PartnerAddressModel
    {
        return new PartnerAddressModel();
    }

    public function index(): void
    {
        $this->authenticate();

        $filters = $this->baseFilters() + [
            'type' => $_GET['type'] ?? '',
            'status' => $_GET['status'] ?? '',
            'customer_group_id' => (int) ($_GET['customer_group_id'] ?? 0),
        ];
        $pager = $this->paginator();
        $rows = $this->partners()->paginate($filters, $pager);

        $this->collection($rows, $pager);
    }

    public function show(int $id): void
    {
        $this->authenticate();

        $partner = $this->partners()->findById($id);
        if (!$partner) {
            $this->error('Partner not found.', 404);
        }
        $this->json(['data' => $this->withAddresses($partner)]);
    }

    public function showByTax(string $taxNumber): void
    {
        $this->authenticate();

        $partner = $this->partners()->findByTaxNumber($taxNumber);
        if (!$partner) {
            $this->error('Partner not found.', 404);
        }
        $this->json(['data' => $this->withAddresses($partner)]);
    }

    public function create(): void
    {
        $this->authenticate();

        $body = $this->body();
        $data = $this->mapBody($body);
        if ($data['name'] === '') {
            $this->error('The name field is required.', 422);
        }

        $id = $this->partners()->create($data);
        $this->replaceAddresses($id, $body);

        $this->json(['data' => $this->withAddresses($this->partners()->findById($id))], 201);
    }

    public function update(int $id): void
    {
        $this->authenticate();

        if (!$this->partners()->findById($id)) {
            $this->error('Partner not found.', 404);
        }
        $this->applyUpdate($id, $this->body());
        $this->json(['data' => $this->withAddresses($this->partners()->findById($id))]);
    }

    /** PUT /api/partners/tax/{tax}: update if exists, otherwise create (upsert). */
    public function upsertByTax(string $taxNumber): void
    {
        $this->authenticate();

        $body = $this->body();
        $existing = $this->partners()->findByTaxNumber($taxNumber);

        if ($existing) {
            $this->applyUpdate((int) $existing['id'], $body);
            $this->json(['data' => $this->withAddresses($this->partners()->findById((int) $existing['id']))]);
        }

        // Create — force the tax number from the URL if the body omits it.
        $body['tax_number'] = $body['tax_number'] ?? $taxNumber;
        $data = $this->mapBody($body);
        if ($data['name'] === '') {
            $this->error('The name field is required.', 422);
        }
        $id = $this->partners()->create($data);
        $this->replaceAddresses($id, $body);
        $this->json(['data' => $this->withAddresses($this->partners()->findById($id))], 201);
    }

    public function delete(int $id): void
    {
        $this->authenticate();

        if (!$this->partners()->findById($id)) {
            $this->error('Partner not found.', 404);
        }
        $this->partners()->delete($id);
        $this->json(['data' => ['deleted' => true, 'id' => $id]]);
    }

    private function applyUpdate(int $id, array $body): void
    {
        $data = $this->mapBody($body);
        if ($data['name'] === '') {
            $this->error('The name field is required.', 422);
        }
        $this->partners()->update($id, $data);
        // Addresses only replaced when the key is present in the body.
        if (array_key_exists('addresses', $body)) {
            $this->replaceAddresses($id, $body, true);
        }
    }

    /** Maps a JSON body to the PartnerModel data shape. */
    private function mapBody(array $body): array
    {
        $type = $body['type'] ?? 'customer';
        return [
            'type' => in_array($type, self::TYPES, true) ? $type : 'customer',
            'customer_group_id' => (int) ($body['customer_group_id'] ?? 0),
            'name' => trim((string) ($body['name'] ?? '')),
            'tax_number' => trim((string) ($body['tax_number'] ?? '')),
            'email' => trim((string) ($body['email'] ?? '')),
            'phone' => trim((string) ($body['phone'] ?? '')),
            'address' => '',
            'is_active' => array_key_exists('is_active', $body) ? (int) (bool) $body['is_active'] : 1,
        ];
    }

    /** (Re)creates the partner's addresses from body['addresses'] if provided. */
    private function replaceAddresses(int $partnerId, array $body, bool $deleteFirst = false): void
    {
        if (!isset($body['addresses']) || !is_array($body['addresses'])) {
            return;
        }
        if ($deleteFirst) {
            foreach ($this->addresses()->forPartner($partnerId) as $a) {
                $this->addresses()->delete((int) $a['id']);
            }
        }
        foreach ($body['addresses'] as $addr) {
            if (!is_array($addr)) {
                continue;
            }
            $city = trim((string) ($addr['city'] ?? ''));
            $postal = trim((string) ($addr['postal_code'] ?? ''));
            $street = trim((string) ($addr['street'] ?? ''));
            if ($city === '' || $postal === '' || $street === '') {
                continue;
            }
            $this->addresses()->create([
                'partner_id' => $partnerId,
                'country' => trim((string) ($addr['country'] ?? '')),
                'city' => $city,
                'postal_code' => $postal,
                'street' => $street,
                'note' => trim((string) ($addr['note'] ?? '')),
            ]);
        }
    }

    private function withAddresses(array $partner): array
    {
        $partner['addresses'] = $this->addresses()->forPartner((int) $partner['id']);
        return $partner;
    }
}
