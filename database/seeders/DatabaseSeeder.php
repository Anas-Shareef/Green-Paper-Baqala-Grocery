<?php

namespace Database\Seeders;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Business Settings
        $settings = [
            'store_name' => 'Baqqala',
            'store_tagline' => 'Your Everyday Grocery, Delivered.',
            'minimum_order_value' => '300.00',
            'default_internal_delivery_cost' => '25.00',
            'currency_symbol' => '₹',
            'whatsapp_access_token' => '',
            'whatsapp_phone_number_id' => '',
            'whatsapp_business_account_id' => '',
        ];

        foreach ($settings as $key => $val) {
            BusinessSetting::set($key, $val);
        }

        // 2. Users
        $admin = User::create([
            'name' => 'Baqqala Admin',
            'email' => 'admin@baqqala.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $cashier = User::create([
            'name' => 'Cashier Staff',
            'email' => 'staff@baqqala.com',
            'password' => Hash::make('password'),
            'role' => 'staff',
            'status' => 'active',
        ]);

        $driver = User::create([
            'name' => 'Delivery Driver',
            'email' => 'driver@baqqala.com',
            'password' => Hash::make('password'),
            'role' => 'delivery',
            'status' => 'active',
        ]);

        // 3. Expense Categories
        $expLogistics = ExpenseCategory::create(['name' => 'Delivery & Logistics', 'description' => 'Vehicle fuel, maintenance & driver allowances']);
        $expUtilities = ExpenseCategory::create(['name' => 'Utilities & Electricity', 'description' => 'Store electricity, internet & water bills']);
        $expPackaging = ExpenseCategory::create(['name' => 'Packaging Materials', 'description' => 'Grocery bags, boxes, tape & ice packs']);
        $expWastage   = ExpenseCategory::create(['name' => 'Wastage & Expiry', 'description' => 'Spoiled produce & expired products']);
        $expOther     = ExpenseCategory::create(['name' => 'Maintenance & Other', 'description' => 'Store repairs and misc expenses']);

        // 4. Categories
        $categoriesData = [
            ['name' => 'Fresh Produce', 'slug' => 'fresh-produce', 'sort_order' => 1, 'image' => 'https://images.unsplash.com/photo-1610832958506-aa56368176cf?auto=format&fit=crop&w=400&q=80'],
            ['name' => 'Dairy & Eggs', 'slug' => 'dairy-eggs', 'sort_order' => 2, 'image' => 'https://images.unsplash.com/photo-1528750997573-59b89d56f4f7?auto=format&fit=crop&w=400&q=80'],
            ['name' => 'Bakery & Breakfast', 'slug' => 'bakery-breakfast', 'sort_order' => 3, 'image' => 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=400&q=80'],
            ['name' => 'Beverages', 'slug' => 'beverages', 'sort_order' => 4, 'image' => 'https://images.unsplash.com/photo-1527661591475-527312dd65f5?auto=format&fit=crop&w=400&q=80'],
            ['name' => 'Snacks & Sweets', 'slug' => 'snacks-sweets', 'sort_order' => 5, 'image' => 'https://images.unsplash.com/photo-1566478989037-eec170784d0b?auto=format&fit=crop&w=400&q=80'],
            ['name' => 'Frozen Food', 'slug' => 'frozen-food', 'sort_order' => 6, 'image' => 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=400&q=80'],
            ['name' => 'Household Care', 'slug' => 'household-care', 'sort_order' => 7, 'image' => 'https://images.unsplash.com/photo-1584820927498-cfe5211fd8bf?auto=format&fit=crop&w=400&q=80'],
            ['name' => 'Personal Care', 'slug' => 'personal-care', 'sort_order' => 8, 'image' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=400&q=80'],
        ];

        $categories = [];
        foreach ($categoriesData as $cat) {
            $categories[$cat['slug']] = Category::create($cat);
        }

        // 5. Products (30 items with real barcodes, wholesale costs, retail prices, units, stock)
        $productsData = [
            // Dairy & Eggs
            [
                'category_slug' => 'dairy-eggs',
                'barcode' => '8901288030609',
                'name' => 'Fresh Whole Milk 1L',
                'brand' => 'Almarai',
                'unit' => '1 Litre',
                'wholesale_cost' => 45.00,
                'retail_price' => 60.00,
                'stock_quantity' => 45,
                'minimum_stock_level' => 10,
                'image' => 'https://images.unsplash.com/photo-1563636619-e9143da7973b?auto=format&fit=crop&w=400&q=80',
            ],
            [
                'category_slug' => 'dairy-eggs',
                'barcode' => '8901030012345',
                'name' => 'Farm Fresh Eggs (30 Pack)',
                'brand' => 'Local Farm',
                'unit' => '30 Eggs',
                'wholesale_cost' => 180.00,
                'retail_price' => 240.00,
                'stock_quantity' => 20,
                'minimum_stock_level' => 5,
                'image' => 'https://images.unsplash.com/photo-1516467508483-a7212febe31a?auto=format&fit=crop&w=400&q=80',
            ],
            [
                'category_slug' => 'dairy-eggs',
                'barcode' => '8901050098765',
                'name' => 'Greek Style Plain Yogurt 500g',
                'brand' => 'Lacnor',
                'unit' => '500 grams',
                'wholesale_cost' => 70.00,
                'retail_price' => 95.00,
                'stock_quantity' => 30,
                'minimum_stock_level' => 8,
                'image' => 'https://images.unsplash.com/photo-1488477181946-6428a0291777?auto=format&fit=crop&w=400&q=80',
            ],
            [
                'category_slug' => 'dairy-eggs',
                'barcode' => '8901111222333',
                'name' => 'Unsalted Creamery Butter 200g',
                'brand' => 'Lurpak',
                'unit' => '200 grams',
                'wholesale_cost' => 110.00,
                'retail_price' => 145.00,
                'stock_quantity' => 25,
                'minimum_stock_level' => 5,
                'image' => 'https://images.unsplash.com/photo-1589985270826-4b7bb135bc9d?auto=format&fit=crop&w=400&q=80',
            ],
            [
                'category_slug' => 'dairy-eggs',
                'barcode' => '8902222333444',
                'name' => 'Cheddar Cheese Slices 200g',
                'brand' => 'Kraft',
                'unit' => '200 grams',
                'wholesale_cost' => 85.00,
                'retail_price' => 115.00,
                'stock_quantity' => 18,
                'minimum_stock_level' => 5,
                'image' => 'https://images.unsplash.com/photo-1618160702438-9b02ab6515c9?auto=format&fit=crop&w=400&q=80',
            ],

            // Bakery & Breakfast
            [
                'category_slug' => 'bakery-breakfast',
                'barcode' => '8901000111222',
                'name' => 'White Sandwich Bread',
                'brand' => 'Modern Bakery',
                'unit' => '1 Pack',
                'wholesale_cost' => 28.00,
                'retail_price' => 40.00,
                'stock_quantity' => 35,
                'minimum_stock_level' => 10,
                'image' => 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=400&q=80',
            ],
            [
                'category_slug' => 'bakery-breakfast',
                'barcode' => '8901000333444',
                'name' => 'Whole Wheat Bread',
                'brand' => 'Modern Bakery',
                'unit' => '1 Pack',
                'wholesale_cost' => 32.00,
                'retail_price' => 45.00,
                'stock_quantity' => 28,
                'minimum_stock_level' => 8,
                'image' => 'https://images.unsplash.com/photo-1549931319-a545dcf3bc73?auto=format&fit=crop&w=400&q=80',
            ],
            [
                'category_slug' => 'bakery-breakfast',
                'barcode' => '8901000555666',
                'name' => 'Butter Croissants (4 Pack)',
                'brand' => 'Baqqala Fresh',
                'unit' => '4 Pieces',
                'wholesale_cost' => 75.00,
                'retail_price' => 110.00,
                'stock_quantity' => 15,
                'minimum_stock_level' => 4,
                'image' => 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?auto=format&fit=crop&w=400&q=80',
            ],
            [
                'category_slug' => 'bakery-breakfast',
                'barcode' => '8901000777888',
                'name' => 'Corn Flakes Cereal 375g',
                'brand' => 'Kellogg\'s',
                'unit' => '375 grams',
                'wholesale_cost' => 140.00,
                'retail_price' => 185.00,
                'stock_quantity' => 22,
                'minimum_stock_level' => 5,
                'image' => 'https://images.unsplash.com/photo-1584473457406-6df376d53de8?auto=format&fit=crop&w=400&q=80',
            ],

            // Fresh Produce
            [
                'category_slug' => 'fresh-produce',
                'barcode' => '8902000111111',
                'name' => 'Red Apples 1kg',
                'brand' => 'Fresh Import',
                'unit' => '1 kg',
                'wholesale_cost' => 90.00,
                'retail_price' => 130.00,
                'stock_quantity' => 50,
                'minimum_stock_level' => 15,
                'image' => 'https://images.unsplash.com/photo-1560806887-1e4cd0b6cbd6?auto=format&fit=crop&w=400&q=80',
            ],
            [
                'category_slug' => 'fresh-produce',
                'barcode' => '8902000222222',
                'name' => 'Yellow Bananas 1kg',
                'brand' => 'Chiquita',
                'unit' => '1 kg',
                'wholesale_cost' => 50.00,
                'retail_price' => 75.00,
                'stock_quantity' => 60,
                'minimum_stock_level' => 20,
                'image' => 'https://images.unsplash.com/photo-1571771894821-ce9b6c11b08e?auto=format&fit=crop&w=400&q=80',
            ],
            [
                'category_slug' => 'fresh-produce',
                'barcode' => '8902000333333',
                'name' => 'Fresh Tomatoes 1kg',
                'brand' => 'Local Farm',
                'unit' => '1 kg',
                'wholesale_cost' => 35.00,
                'retail_price' => 55.00,
                'stock_quantity' => 40,
                'minimum_stock_level' => 12,
                'image' => 'https://images.unsplash.com/photo-1592924357228-91a4daadcfea?auto=format&fit=crop&w=400&q=80',
            ],
            [
                'category_slug' => 'fresh-produce',
                'barcode' => '8902000444444',
                'name' => 'Red Onions 1kg',
                'brand' => 'Local Farm',
                'unit' => '1 kg',
                'wholesale_cost' => 30.00,
                'retail_price' => 48.00,
                'stock_quantity' => 50,
                'minimum_stock_level' => 15,
                'image' => 'https://images.unsplash.com/photo-1618512496248-a07fe83aa8cf?auto=format&fit=crop&w=400&q=80',
            ],
            [
                'category_slug' => 'fresh-produce',
                'barcode' => '8902000555555',
                'name' => 'Washed Potatoes 2kg',
                'brand' => 'Local Farm',
                'unit' => '2 kg',
                'wholesale_cost' => 60.00,
                'retail_price' => 90.00,
                'stock_quantity' => 35,
                'minimum_stock_level' => 10,
                'image' => 'https://images.unsplash.com/photo-1518977676601-b53f82aba655?auto=format&fit=crop&w=400&q=80',
            ],

            // Beverages
            [
                'category_slug' => 'beverages',
                'barcode' => '8903000111111',
                'name' => 'Pure Drinking Water 5L',
                'brand' => 'Mai Dubai',
                'unit' => '5 Litres',
                'wholesale_cost' => 40.00,
                'retail_price' => 65.00,
                'stock_quantity' => 80,
                'minimum_stock_level' => 25,
                'image' => 'https://images.unsplash.com/photo-1548839140-29a749e1bc4e?auto=format&fit=crop&w=400&q=80',
            ],
            [
                'category_slug' => 'beverages',
                'barcode' => '8903000222222',
                'name' => '100% Orange Juice 1L',
                'brand' => 'Lacnor',
                'unit' => '1 Litre',
                'wholesale_cost' => 85.00,
                'retail_price' => 120.00,
                'stock_quantity' => 40,
                'minimum_stock_level' => 10,
                'image' => 'https://images.unsplash.com/photo-1613478223719-2ab802602423?auto=format&fit=crop&w=400&q=80',
            ],
            [
                'category_slug' => 'beverages',
                'barcode' => '8903000333333',
                'name' => 'Classic Cola 1.5L',
                'brand' => 'Coca-Cola',
                'unit' => '1.5 Litres',
                'wholesale_cost' => 45.00,
                'retail_price' => 65.00,
                'stock_quantity' => 50,
                'minimum_stock_level' => 15,
                'image' => 'https://images.unsplash.com/photo-1622483767028-3f66f32aef97?auto=format&fit=crop&w=400&q=80',
            ],
            [
                'category_slug' => 'beverages',
                'barcode' => '8903000444444',
                'name' => 'Black Tea Bags (100 Pack)',
                'brand' => 'Lipton',
                'unit' => '100 Bags',
                'wholesale_cost' => 120.00,
                'retail_price' => 165.00,
                'stock_quantity' => 30,
                'minimum_stock_level' => 8,
                'image' => 'https://images.unsplash.com/photo-1576092768241-dec231879fc3?auto=format&fit=crop&w=400&q=80',
            ],
            [
                'category_slug' => 'beverages',
                'barcode' => '8903000555555',
                'name' => 'Instant Coffee Gold 100g',
                'brand' => 'Nescafe',
                'unit' => '100 grams',
                'wholesale_cost' => 175.00,
                'retail_price' => 235.00,
                'stock_quantity' => 20,
                'minimum_stock_level' => 5,
                'image' => 'https://images.unsplash.com/photo-1559056199-641a0ac8b55e?auto=format&fit=crop&w=400&q=80',
            ],

            // Snacks & Sweets
            [
                'category_slug' => 'snacks-sweets',
                'barcode' => '8904000111111',
                'name' => 'Classic Potato Chips 160g',
                'brand' => 'Lay\'s',
                'unit' => '160 grams',
                'wholesale_cost' => 45.00,
                'retail_price' => 65.00,
                'stock_quantity' => 60,
                'minimum_stock_level' => 15,
                'image' => 'https://images.unsplash.com/photo-1566478989037-eec170784d0b?auto=format&fit=crop&w=400&q=80',
            ],
            [
                'category_slug' => 'snacks-sweets',
                'barcode' => '8904000222222',
                'name' => 'Milk Chocolate Bar 100g',
                'brand' => 'Cadbury',
                'unit' => '100 grams',
                'wholesale_cost' => 55.00,
                'retail_price' => 80.00,
                'stock_quantity' => 45,
                'minimum_stock_level' => 10,
                'image' => 'https://images.unsplash.com/photo-1582176604856-e822b3711904?auto=format&fit=crop&w=400&q=80',
            ],
            [
                'category_slug' => 'snacks-sweets',
                'barcode' => '8904000333333',
                'name' => 'Digestive Biscuits 400g',
                'brand' => 'McVitie\'s',
                'unit' => '400 grams',
                'wholesale_cost' => 65.00,
                'retail_price' => 95.00,
                'stock_quantity' => 35,
                'minimum_stock_level' => 8,
                'image' => 'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?auto=format&fit=crop&w=400&q=80',
            ],

            // Frozen Food
            [
                'category_slug' => 'frozen-food',
                'barcode' => '8905000111111',
                'name' => 'Frozen Chicken Breast 1kg',
                'brand' => 'Sadia',
                'unit' => '1 kg',
                'wholesale_cost' => 210.00,
                'retail_price' => 280.00,
                'stock_quantity' => 25,
                'minimum_stock_level' => 5,
                'image' => 'https://images.unsplash.com/photo-1604503468506-a8da13d82791?auto=format&fit=crop&w=400&q=80',
            ],
            [
                'category_slug' => 'frozen-food',
                'barcode' => '8905000222222',
                'name' => 'Vanilla Ice Cream 1L',
                'brand' => 'Baskin-Robbins',
                'unit' => '1 Litre',
                'wholesale_cost' => 160.00,
                'retail_price' => 220.00,
                'stock_quantity' => 18,
                'minimum_stock_level' => 5,
                'image' => 'https://images.unsplash.com/photo-1570197788417-0e82375c9371?auto=format&fit=crop&w=400&q=80',
            ],

            // Household Care
            [
                'category_slug' => 'household-care',
                'barcode' => '8906000111111',
                'name' => 'Liquid Laundry Detergent 3L',
                'brand' => 'Ariel',
                'unit' => '3 Litres',
                'wholesale_cost' => 290.00,
                'retail_price' => 395.00,
                'stock_quantity' => 20,
                'minimum_stock_level' => 4,
                'image' => 'https://images.unsplash.com/photo-1584820927498-cfe5211fd8bf?auto=format&fit=crop&w=400&q=80',
            ],
            [
                'category_slug' => 'household-care',
                'barcode' => '8906000222222',
                'name' => 'Dishwashing Liquid 1L',
                'brand' => 'Fairy',
                'unit' => '1 Litre',
                'wholesale_cost' => 95.00,
                'retail_price' => 135.00,
                'stock_quantity' => 30,
                'minimum_stock_level' => 8,
                'image' => 'https://images.unsplash.com/photo-1585830810419-7ac6e4814166?auto=format&fit=crop&w=400&q=80',
            ],

            // Personal Care
            [
                'category_slug' => 'personal-care',
                'barcode' => '8907000111111',
                'name' => 'Anti-Dandruff Shampoo 400ml',
                'brand' => 'Head & Shoulders',
                'unit' => '400 ml',
                'wholesale_cost' => 135.00,
                'retail_price' => 185.00,
                'stock_quantity' => 22,
                'minimum_stock_level' => 5,
                'image' => 'https://images.unsplash.com/photo-1535585209827-a15fcdbc4c2d?auto=format&fit=crop&w=400&q=80',
            ],
        ];

        $products = [];
        foreach ($productsData as $pData) {
            $catSlug = $pData['category_slug'];
            unset($pData['category_slug']);
            $pData['category_id'] = $categories[$catSlug]->id;
            $product = Product::create($pData);
            $products[] = $product;

            // Log initial inventory receiving movement
            StockMovement::create([
                'product_id' => $product->id,
                'type' => 'Purchase',
                'quantity' => $product->stock_quantity,
                'stock_before' => 0,
                'stock_after' => $product->stock_quantity,
                'reference_type' => 'Receiving',
                'reason' => 'Initial Inventory Setup',
                'created_by' => 'System Admin',
            ]);
        }

        // 6. Customers
        $customersData = [
            ['name' => 'Muhammed Al Nuaimi', 'phone' => '971501112233', 'villa_number' => 'Villa 12', 'zone' => 'Zone A', 'address' => 'Street 4, Villa 12, Zone A'],
            ['name' => 'Sarah Johnson', 'phone' => '971502223344', 'villa_number' => 'Villa 45', 'zone' => 'Zone B', 'address' => 'Street 9, Villa 45, Zone B'],
            ['name' => 'Rajesh Kumar', 'phone' => '971503334455', 'villa_number' => 'Villa 108', 'zone' => 'Zone A', 'address' => 'Street 2, Villa 108, Zone A'],
            ['name' => 'Fatima Hassan', 'phone' => '971504445566', 'villa_number' => 'Villa 7', 'zone' => 'Zone C', 'address' => 'Street 1, Villa 7, Zone C'],
            ['name' => 'John Smith', 'phone' => '971505556677', 'villa_number' => 'Villa 89', 'zone' => 'Zone B', 'address' => 'Street 14, Villa 89, Zone B'],
            ['name' => 'Ahmed Mansoor', 'phone' => '971506667788', 'villa_number' => 'Villa 203', 'zone' => 'Zone A', 'address' => 'Street 5, Villa 203, Zone A'],
            ['name' => 'Priya Sharma', 'phone' => '971507778899', 'villa_number' => 'Villa 15', 'zone' => 'Zone C', 'address' => 'Street 3, Villa 15, Zone C'],
            ['name' => 'Abdullah Omar', 'phone' => '971508889900', 'villa_number' => 'Villa 94', 'zone' => 'Zone B', 'address' => 'Street 11, Villa 94, Zone B'],
        ];

        $customers = [];
        foreach ($customersData as $cData) {
            $cData['whatsapp_number'] = $cData['phone'];
            $customers[] = Customer::create($cData);
        }

        // 7. Seed Sample Expenses
        Expense::create([
            'expense_category_id' => $expLogistics->id,
            'amount' => 150.00,
            'description' => 'Delivery scooter fuel fill up',
            'expense_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'created_by' => 'Baqqala Admin',
        ]);

        Expense::create([
            'expense_category_id' => $expPackaging->id,
            'amount' => 200.00,
            'description' => '500 Biodegradable grocery bags batch',
            'expense_date' => now()->subDays(2)->toDateString(),
            'payment_method' => 'Cash',
            'created_by' => 'Baqqala Admin',
        ]);

        // 8. Seed Historical Orders for Dashboard Analytics
        $milk = $products[0];
        $eggs = $products[1];
        $bread = $products[5];
        $water = $products[14];

        // Sample Order 1: Delivered Online Order
        $order1 = Order::create([
            'order_number' => 'ORD-000101',
            'customer_id' => $customers[0]->id,
            'subtotal' => 400.00,
            'discount_amount' => 0.00,
            'delivery_charge' => 0.00,
            'total_amount' => 400.00,
            'internal_delivery_cost' => 25.00,
            'product_cost' => 303.00,
            'gross_profit' => 97.00,
            'net_profit' => 72.00,
            'payment_method' => 'Cash',
            'payment_status' => 'paid',
            'status' => 'delivered',
            'order_source' => 'PWA',
            'delivery_staff_id' => $driver->id,
            'customer_villa' => 'Villa 12',
            'customer_address' => 'Street 4, Villa 12, Zone A',
            'delivered_at' => now()->subHours(3),
        ]);

        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $eggs->id,
            'product_name' => $eggs->name,
            'quantity' => 1,
            'unit_price' => $eggs->retail_price,
            'wholesale_cost' => $eggs->wholesale_cost,
            'total' => $eggs->retail_price,
        ]);

        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $milk->id,
            'product_name' => $milk->name,
            'quantity' => 2,
            'unit_price' => $milk->retail_price,
            'wholesale_cost' => $milk->wholesale_cost,
            'total' => $milk->retail_price * 2,
        ]);

        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $bread->id,
            'product_name' => $bread->name,
            'quantity' => 1,
            'unit_price' => $bread->retail_price,
            'wholesale_cost' => $bread->wholesale_cost,
            'total' => $bread->retail_price,
        ]);

        // Sample Order 2: Walk-in POS Sale
        $order2 = Order::create([
            'order_number' => 'ORD-000102',
            'customer_id' => null,
            'subtotal' => 125.00,
            'discount_amount' => 0.00,
            'delivery_charge' => 0.00,
            'total_amount' => 125.00,
            'internal_delivery_cost' => 0.00,
            'product_cost' => 88.00,
            'gross_profit' => 37.00,
            'net_profit' => 37.00,
            'payment_method' => 'Cash',
            'payment_status' => 'paid',
            'status' => 'delivered',
            'order_source' => 'POS',
            'delivered_at' => now()->subHours(1),
        ]);

        OrderItem::create([
            'order_id' => $order2->id,
            'product_id' => $water->id,
            'product_name' => $water->name,
            'quantity' => 1,
            'unit_price' => $water->retail_price,
            'wholesale_cost' => $water->wholesale_cost,
            'total' => $water->retail_price,
        ]);

        OrderItem::create([
            'order_id' => $order2->id,
            'product_id' => $milk->id,
            'product_name' => $milk->name,
            'quantity' => 1,
            'unit_price' => $milk->retail_price,
            'wholesale_cost' => $milk->wholesale_cost,
            'total' => $milk->retail_price,
        ]);

        // Sample Order 3: Pending Online Order
        $order3 = Order::create([
            'order_number' => 'ORD-000103',
            'customer_id' => $customers[1]->id,
            'subtotal' => 465.00,
            'discount_amount' => 0.00,
            'delivery_charge' => 0.00,
            'total_amount' => 465.00,
            'internal_delivery_cost' => 25.00,
            'product_cost' => 335.00,
            'gross_profit' => 130.00,
            'net_profit' => 105.00,
            'payment_method' => 'Cash',
            'payment_status' => 'pending',
            'status' => 'pending',
            'order_source' => 'PWA',
            'customer_villa' => 'Villa 45',
            'customer_address' => 'Street 9, Villa 45, Zone B',
        ]);

        OrderItem::create([
            'order_id' => $order3->id,
            'product_id' => $eggs->id,
            'product_name' => $eggs->name,
            'quantity' => 1,
            'unit_price' => $eggs->retail_price,
            'wholesale_cost' => $eggs->wholesale_cost,
            'total' => $eggs->retail_price,
        ]);

        OrderItem::create([
            'order_id' => $order3->id,
            'product_id' => $products[22]->id, // Chicken Breast
            'product_name' => $products[22]->name,
            'quantity' => 1,
            'unit_price' => $products[22]->retail_price,
            'wholesale_cost' => $products[22]->wholesale_cost,
            'total' => $products[22]->retail_price,
        ]);
    }
}
