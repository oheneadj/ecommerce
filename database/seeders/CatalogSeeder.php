<?php

/**
 * Seeds a realistic catalog: categories (one nested), brands, and products
 * with variants at deliberately varied stock levels so the low-stock
 * dashboard/alerts have something real to show.
 */

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Catalog\CreateProduct;
use App\Actions\Inventory\RecordStockMovement;
use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $electronics = Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);
        Category::factory()->create(['name' => 'Phones & Tablets', 'slug' => 'phones-tablets', 'parent_id' => $electronics->id]);
        $fashion = Category::factory()->create(['name' => 'Fashion', 'slug' => 'fashion']);
        $home = Category::factory()->create(['name' => 'Home & Kitchen', 'slug' => 'home-kitchen']);

        $techBrand = Brand::factory()->create(['name' => 'Volta Electronics', 'slug' => 'volta-electronics']);
        $fashionBrand = Brand::factory()->create(['name' => 'Accra Threads', 'slug' => 'accra-threads']);

        // Healthy stock, multiple variants.
        $phone = CreateProduct::run([
            'category_id' => $electronics->id,
            'brand_id' => $techBrand->id,
            'name' => 'Nova X12 Smartphone',
            'slug' => 'nova-x12-smartphone',
            'description' => 'A reliable everyday smartphone with a large battery and dual camera.',
            'status' => 'active',
        ], [
            ['sku' => 'NOVA-X12-64', 'price' => 189900, 'stock' => 0],
            ['sku' => 'NOVA-X12-128', 'price' => 219900, 'stock' => 0],
        ]);

        foreach ($phone->variants as $variant) {
            RecordStockMovement::run($variant, StockMovementType::Restock, 40, note: 'Initial stock (seed data)');
        }

        // Low stock — will show up on the dashboard/Store Keeper alerts.
        $headphones = CreateProduct::run([
            'category_id' => $electronics->id,
            'brand_id' => $techBrand->id,
            'name' => 'Volta SoundMax Headphones',
            'slug' => 'volta-soundmax-headphones',
            'description' => 'Over-ear wireless headphones with active noise cancellation.',
            'status' => 'active',
        ], [
            ['sku' => 'VOLTA-SM-BLK', 'price' => 45000, 'stock' => 0],
        ]);
        RecordStockMovement::run($headphones->variants->first(), StockMovementType::Restock, 3, note: 'Initial stock (seed data) — deliberately low');

        // Out of stock entirely.
        $charger = CreateProduct::run([
            'category_id' => $electronics->id,
            'brand_id' => $techBrand->id,
            'name' => 'Volta 65W Fast Charger',
            'slug' => 'volta-65w-fast-charger',
            'description' => 'Compact GaN fast charger, compatible with most phones and laptops.',
            'status' => 'active',
        ], [
            ['sku' => 'VOLTA-CHG-65W', 'price' => 15000, 'stock' => 0],
        ]);
        // Deliberately left at 0 stock — no restock movement.
        unset($charger);

        // Fashion product with size variants.
        $shirt = CreateProduct::run([
            'category_id' => $fashion->id,
            'brand_id' => $fashionBrand->id,
            'name' => 'Kente-Trim Cotton Shirt',
            'slug' => 'kente-trim-cotton-shirt',
            'description' => 'A breathable cotton shirt with a hand-woven kente trim collar.',
            'status' => 'active',
        ], [
            ['sku' => 'KENTE-SHIRT-M', 'price' => 32000, 'stock' => 0],
            ['sku' => 'KENTE-SHIRT-L', 'price' => 32000, 'stock' => 0],
            ['sku' => 'KENTE-SHIRT-XL', 'price' => 32000, 'stock' => 0],
        ]);

        foreach ($shirt->variants as $variant) {
            RecordStockMovement::run($variant, StockMovementType::Restock, 25, note: 'Initial stock (seed data)');
        }

        // Home & Kitchen product, no brand.
        $blender = CreateProduct::run([
            'category_id' => $home->id,
            'name' => 'PowerBlend 500 Blender',
            'slug' => 'powerblend-500-blender',
            'description' => 'A 500W countertop blender for smoothies, soups, and sauces.',
            'status' => 'active',
        ], [
            ['sku' => 'PB500-BLENDER', 'price' => 28000, 'stock' => 0],
        ]);
        RecordStockMovement::run($blender->variants->first(), StockMovementType::Restock, 60, note: 'Initial stock (seed data)');

        // A draft product — should never appear in customer-facing listings.
        CreateProduct::run([
            'category_id' => $home->id,
            'name' => 'Unreleased Air Fryer Pro',
            'slug' => 'unreleased-air-fryer-pro',
            'status' => 'draft',
        ], [
            ['sku' => 'AIRFRYER-PRO-DRAFT', 'price' => 65000, 'stock' => 10],
        ]);
    }
}
