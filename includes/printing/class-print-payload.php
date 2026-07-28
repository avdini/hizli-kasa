<?php
if (!defined('ABSPATH')) exit;

class Hizli_Kasa_Print_Payload {
    private $type;
    private $variant;
    private $order_id;
    private $header = [];
    private $items = [];
    private $totals = [];
    private $audit_trail = [];
    private $barcode_value = '';
    private $extra_data = [];

    public function __construct(string $type, string $variant = 'original', int $order_id = 0) {
        $this->type = $type;
        $this->variant = $variant;
        $this->order_id = $order_id;
    }

    public function set_header(array $header): self {
        $this->header = $header;
        return $this;
    }

    public function set_items(array $items): self {
        $this->items = $items;
        return $this;
    }

    public function set_totals(array $totals): self {
        $this->totals = $totals;
        return $this;
    }

    public function set_audit_trail(array $audit_trail): self {
        $this->audit_trail = $audit_trail;
        return $this;
    }

    public function set_barcode_value(string $barcode): self {
        $this->barcode_value = $barcode;
        return $this;
    }

    public function set_extra_data(array $extra): self {
        $this->extra_data = $extra;
        return $this;
    }

    public function get_type(): string {
        return $this->type;
    }

    public function get_variant(): string {
        return $this->variant;
    }

    public function get_order_id(): int {
        return $this->order_id;
    }

    public function get_header(): array {
        return $this->header;
    }

    public function get_items(): array {
        return $this->items;
    }

    public function get_totals(): array {
        return $this->totals;
    }

    public function get_audit_trail(): array {
        return $this->audit_trail;
    }

    public function get_barcode_value(): string {
        return $this->barcode_value;
    }

    public function get_extra_data(): array {
        return $this->extra_data;
    }

    public function get_template_name(): string {
        if ($this->type === 'order') {
            return $this->variant === 'modified' ? 'receipt-modified' : 'receipt-original';
        }
        if ($this->type === 'zreport') {
            return 'receipt-zreport';
        }
        if ($this->type === 'barcode') {
            return 'label-barcode';
        }
        return 'receipt-original';
    }

    public function to_array(): array {
        return [
            'type'          => $this->type,
            'variant'       => $this->variant,
            'order_id'      => $this->order_id,
            'header'        => $this->header,
            'items'         => $this->items,
            'totals'        => $this->totals,
            'audit_trail'   => $this->audit_trail,
            'barcode_value' => $this->barcode_value,
            'extra_data'    => $this->extra_data,
            'template_name' => $this->get_template_name()
        ];
    }
}
