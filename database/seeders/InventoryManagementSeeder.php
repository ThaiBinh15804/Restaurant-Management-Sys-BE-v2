<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\StockExport;
use App\Models\StockExportDetail;
use App\Models\StockImport;
use App\Models\StockImportDetail;
use App\Models\StockLoss;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class InventoryManagementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = \Carbon\Carbon::now();
        
        // Get employee for audit trail
        $adminEmployee = Employee::whereHas('user', function ($query) {
            $query->where('email', 'admin@restaurant.com');
        })->first();

        if (!$adminEmployee) {
            throw new \Exception('Admin employee not found. Please run EmployeeManagementSeeder first.');
        }

        // 1. Create Suppliers
        $suppliers = $this->createSuppliers($adminEmployee);

        // 2. Create Ingredients
        $ingredients = $this->createIngredients($adminEmployee);

        // 3. Create Stock Imports with Details (nhập kho)
        $this->createStockImports($suppliers, $ingredients, $adminEmployee, $now);

        // 4. Create Stock Exports with Details (xuất kho)
        $this->createStockExports($ingredients, $adminEmployee, $now);

        // 5. Create Stock Losses (hao hụt)
        $this->createStockLosses($ingredients, $adminEmployee, $now);
    }

    /**
     * Create suppliers
     */
    private function createSuppliers($employee): array
    {
        $suppliers = [
            [
                'name' => 'Công Ty Thực Phẩm Tươi Sống',
                'phone' => '0901234567',
                'contact_person_name' => 'Nguyễn Văn A',
                'contact_person_phone' => '0901234567',
                'email' => 'lienhe@thucphamtuoisong.com',
                'address' => '123 Nguyễn Huệ, Quận 1, TP. Hồ Chí Minh',
                'is_active' => true,
                'created_by' => $employee->id,
                'updated_by' => $employee->id,
            ],
            [
                'name' => 'Nhà Phân Phối Thịt & Hải Sản',
                'phone' => '0909876543',
                'contact_person_name' => 'Trần Thị B',
                'contact_person_phone' => '0909876543',
                'email' => 'banhang@thithaisan.com',
                'address' => '456 Lê Lại, Quận 3, TP. Hồ Chí Minh',
                'is_active' => true,
                'created_by' => $employee->id,
                'updated_by' => $employee->id,
            ],
            [
                'name' => 'Công Ty Gia Vị & Thảo Mộc',
                'phone' => '0912345678',
                'contact_person_name' => 'Lê Văn C',
                'contact_person_phone' => '0912345678',
                'email' => 'thongtin@giavithaomoc.com',
                'address' => '789 Trần Hưng Đạo, Quận 5, TP. Hồ Chí Minh',
                'is_active' => true,
                'created_by' => $employee->id,
                'updated_by' => $employee->id,
            ],
            [
                'name' => 'Trung Tâm Phân Phối Đồ Uống',
                'phone' => '0987654321',
                'contact_person_name' => 'Phạm Thị D',
                'contact_person_phone' => '0987654321',
                'email' => 'donhang@douong.com',
                'address' => '321 Võ Văn Tần, Quận 10, TP. Hồ Chí Minh',
                'is_active' => true,
                'created_by' => $employee->id,
                'updated_by' => $employee->id,
            ],
        ];

        $createdSuppliers = [];
        foreach ($suppliers as $supplierData) {
            $createdSuppliers[] = Supplier::create($supplierData);
        }

        return $createdSuppliers;
    }

    /**
     * Create ingredients with initial stock = 0
     */
    private function createIngredients($employee): array
    {
        $ingredients = [
            // Rau củ & Thảo mộc
            ['name' => 'Cà chua', 'unit' => 'kg', 'min_stock' => 10, 'max_stock' => 50],
            ['name' => 'Hành tây', 'unit' => 'kg', 'min_stock' => 15, 'max_stock' => 60],
            ['name' => 'Tỏi', 'unit' => 'kg', 'min_stock' => 5, 'max_stock' => 20],
            ['name' => 'Cà rốt', 'unit' => 'kg', 'min_stock' => 10, 'max_stock' => 40],
            ['name' => 'Ớt chuông', 'unit' => 'kg', 'min_stock' => 8, 'max_stock' => 30],
            ['name' => 'Xà lách', 'unit' => 'kg', 'min_stock' => 5, 'max_stock' => 20],
            ['name' => 'Rau mùi', 'unit' => 'kg', 'min_stock' => 2, 'max_stock' => 10],
            ['name' => 'Húng quế', 'unit' => 'kg', 'min_stock' => 2, 'max_stock' => 8],
            
            // Thịt & Hải sản
            ['name' => 'Ức gà', 'unit' => 'kg', 'min_stock' => 20, 'max_stock' => 80],
            ['name' => 'Thịt bò thăn', 'unit' => 'kg', 'min_stock' => 15, 'max_stock' => 60],
            ['name' => 'Thịt vai heo', 'unit' => 'kg', 'min_stock' => 15, 'max_stock' => 60],
            ['name' => 'Phi lê cá hồi', 'unit' => 'kg', 'min_stock' => 10, 'max_stock' => 40],
            ['name' => 'Tôm sú', 'unit' => 'kg', 'min_stock' => 12, 'max_stock' => 50],
            
            // Sữa & Trứng
            ['name' => 'Sữa tươi', 'unit' => 'lít', 'min_stock' => 20, 'max_stock' => 80],
            ['name' => 'Bơ', 'unit' => 'kg', 'min_stock' => 5, 'max_stock' => 20],
            ['name' => 'Phô mai', 'unit' => 'kg', 'min_stock' => 8, 'max_stock' => 30],
            ['name' => 'Trứng gà', 'unit' => 'chục', 'min_stock' => 30, 'max_stock' => 100],
            
            // Gạo & Mì
            ['name' => 'Gạo', 'unit' => 'kg', 'min_stock' => 50, 'max_stock' => 200],
            ['name' => 'Mì Ý', 'unit' => 'kg', 'min_stock' => 20, 'max_stock' => 80],
            ['name' => 'Bột mì', 'unit' => 'kg', 'min_stock' => 30, 'max_stock' => 100],
            
            // Gia vị & Nước chấm
            ['name' => 'Muối', 'unit' => 'kg', 'min_stock' => 10, 'max_stock' => 40],
            ['name' => 'Tiêu đen', 'unit' => 'kg', 'min_stock' => 2, 'max_stock' => 10],
            ['name' => 'Đường', 'unit' => 'kg', 'min_stock' => 15, 'max_stock' => 60],
            ['name' => 'Nước tương', 'unit' => 'lít', 'min_stock' => 10, 'max_stock' => 40],
            ['name' => 'Nước mắm', 'unit' => 'lít', 'min_stock' => 10, 'max_stock' => 40],
            ['name' => 'Dầu ô liu', 'unit' => 'lít', 'min_stock' => 8, 'max_stock' => 30],
            ['name' => 'Dầu ăn', 'unit' => 'lít', 'min_stock' => 15, 'max_stock' => 60],
            
            // Đồ uống
            ['name' => 'Nước cam ép', 'unit' => 'lít', 'min_stock' => 20, 'max_stock' => 80],
            ['name' => 'Hạt cà phê', 'unit' => 'kg', 'min_stock' => 10, 'max_stock' => 40],
            ['name' => 'Lá trà', 'unit' => 'kg', 'min_stock' => 5, 'max_stock' => 20],
        ];

        $createdIngredients = [];
        foreach ($ingredients as $ingredientData) {
            $createdIngredients[] = Ingredient::create([
                'name' => $ingredientData['name'],
                'unit' => $ingredientData['unit'],
                'current_stock' => 0, // Start with 0, will be updated by imports
                'min_stock' => $ingredientData['min_stock'],
                'max_stock' => $ingredientData['max_stock'],
                'is_active' => true,
                'created_by' => $employee->id,
                'updated_by' => $employee->id,
            ]);
        }

        return $createdIngredients;
    }

    /**
     * Create stock imports with details
     */
    private function createStockImports($suppliers, $ingredients, $employee, $now): void
    {
        // Nhập 1: Rau củ và thảo mộc tươi (7 ngày trước)
        $import1 = StockImport::create([
            'import_date' => $now->copy()->subDays(7),
            'total_amount' => 0, // Sẽ được tính toán
            'supplier_id' => $suppliers[0]->id,
            'created_by' => $employee->id,
            'updated_by' => $employee->id,
        ]);

        $import1Details = [
            ['ingredient' => $ingredients[0], 'ordered' => 30, 'received' => 30, 'unit_price' => 15000], // Cà chua
            ['ingredient' => $ingredients[1], 'ordered' => 40, 'received' => 40, 'unit_price' => 12000], // Hành tây
            ['ingredient' => $ingredients[2], 'ordered' => 15, 'received' => 15, 'unit_price' => 45000], // Tỏi
            ['ingredient' => $ingredients[3], 'ordered' => 25, 'received' => 25, 'unit_price' => 18000], // Cà rốt
            ['ingredient' => $ingredients[6], 'ordered' => 5, 'received' => 5, 'unit_price' => 30000], // Rau mùi
            ['ingredient' => $ingredients[7], 'ordered' => 4, 'received' => 4, 'unit_price' => 35000], // Húng quế
        ];

        $totalAmount1 = 0;
        foreach ($import1Details as $detail) {
            $totalPrice = $detail['received'] * $detail['unit_price'];
            StockImportDetail::create([
                'ordered_quantity' => $detail['ordered'],
                'received_quantity' => $detail['received'],
                'unit_price' => $detail['unit_price'],
                'total_price' => $totalPrice,
                'stock_import_id' => $import1->id,
                'ingredient_id' => $detail['ingredient']->id,
                'created_by' => $employee->id,
                'updated_by' => $employee->id,
            ]);

            // Cập nhật tồn kho nguyên liệu
            $detail['ingredient']->increment('current_stock', $detail['received']);
            $totalAmount1 += $totalPrice;
        }

        $import1->update(['total_amount' => $totalAmount1]);

        // Nhập 2: Thịt và hải sản (5 ngày trước)
        $import2 = StockImport::create([
            'import_date' => $now->copy()->subDays(5),
            'total_amount' => 0,
            'supplier_id' => $suppliers[1]->id,
            'created_by' => $employee->id,
            'updated_by' => $employee->id,
        ]);

        $import2Details = [
            ['ingredient' => $ingredients[8], 'ordered' => 50, 'received' => 50, 'unit_price' => 85000], // Ức gà
            ['ingredient' => $ingredients[9], 'ordered' => 40, 'received' => 38, 'unit_price' => 250000], // Thịt bò (thiếu 2kg)
            ['ingredient' => $ingredients[10], 'ordered' => 40, 'received' => 40, 'unit_price' => 120000], // Thịt heo
            ['ingredient' => $ingredients[11], 'ordered' => 30, 'received' => 30, 'unit_price' => 350000], // Cá hồi
            ['ingredient' => $ingredients[12], 'ordered' => 35, 'received' => 35, 'unit_price' => 280000], // Tôm sú
        ];

        $totalAmount2 = 0;
        foreach ($import2Details as $detail) {
            $totalPrice = $detail['received'] * $detail['unit_price'];
            StockImportDetail::create([
                'ordered_quantity' => $detail['ordered'],
                'received_quantity' => $detail['received'],
                'unit_price' => $detail['unit_price'],
                'total_price' => $totalPrice,
                'stock_import_id' => $import2->id,
                'ingredient_id' => $detail['ingredient']->id,
                'created_by' => $employee->id,
                'updated_by' => $employee->id,
            ]);

            $detail['ingredient']->increment('current_stock', $detail['received']);
            $totalAmount2 += $totalPrice;
        }

        $import2->update(['total_amount' => $totalAmount2]);

        // Nhập 3: Sữa và ngũ cốc (3 ngày trước)
        $import3 = StockImport::create([
            'import_date' => $now->copy()->subDays(3),
            'total_amount' => 0,
            'supplier_id' => $suppliers[0]->id,
            'created_by' => $employee->id,
            'updated_by' => $employee->id,
        ]);

        $import3Details = [
            ['ingredient' => $ingredients[13], 'ordered' => 50, 'received' => 50, 'unit_price' => 25000], // Sữa tươi
            ['ingredient' => $ingredients[14], 'ordered' => 15, 'received' => 15, 'unit_price' => 180000], // Bơ
            ['ingredient' => $ingredients[15], 'ordered' => 20, 'received' => 20, 'unit_price' => 200000], // Phô mai
            ['ingredient' => $ingredients[16], 'ordered' => 60, 'received' => 60, 'unit_price' => 35000], // Trứng gà
            ['ingredient' => $ingredients[17], 'ordered' => 100, 'received' => 100, 'unit_price' => 18000], // Gạo
            ['ingredient' => $ingredients[18], 'ordered' => 50, 'received' => 50, 'unit_price' => 32000], // Mì Ý
        ];

        $totalAmount3 = 0;
        foreach ($import3Details as $detail) {
            $totalPrice = $detail['received'] * $detail['unit_price'];
            StockImportDetail::create([
                'ordered_quantity' => $detail['ordered'],
                'received_quantity' => $detail['received'],
                'unit_price' => $detail['unit_price'],
                'total_price' => $totalPrice,
                'stock_import_id' => $import3->id,
                'ingredient_id' => $detail['ingredient']->id,
                'created_by' => $employee->id,
                'updated_by' => $employee->id,
            ]);

            $detail['ingredient']->increment('current_stock', $detail['received']);
            $totalAmount3 += $totalPrice;
        }

        $import3->update(['total_amount' => $totalAmount3]);

        // Nhập 4: Gia vị và nước chấm (2 ngày trước)
        $import4 = StockImport::create([
            'import_date' => $now->copy()->subDays(2),
            'total_amount' => 0,
            'supplier_id' => $suppliers[2]->id,
            'created_by' => $employee->id,
            'updated_by' => $employee->id,
        ]);

        $import4Details = [
            ['ingredient' => $ingredients[20], 'ordered' => 20, 'received' => 20, 'unit_price' => 8000], // Muối
            ['ingredient' => $ingredients[21], 'ordered' => 8, 'received' => 8, 'unit_price' => 150000], // Tiêu đen
            ['ingredient' => $ingredients[22], 'ordered' => 40, 'received' => 40, 'unit_price' => 15000], // Đường
            ['ingredient' => $ingredients[23], 'ordered' => 25, 'received' => 25, 'unit_price' => 35000], // Nước tương
            ['ingredient' => $ingredients[24], 'ordered' => 25, 'received' => 25, 'unit_price' => 42000], // Nước mắm
            ['ingredient' => $ingredients[25], 'ordered' => 20, 'received' => 20, 'unit_price' => 120000], // Dầu ô liu
            ['ingredient' => $ingredients[26], 'ordered' => 40, 'received' => 40, 'unit_price' => 45000], // Dầu ăn
        ];

        $totalAmount4 = 0;
        foreach ($import4Details as $detail) {
            $totalPrice = $detail['received'] * $detail['unit_price'];
            StockImportDetail::create([
                'ordered_quantity' => $detail['ordered'],
                'received_quantity' => $detail['received'],
                'unit_price' => $detail['unit_price'],
                'total_price' => $totalPrice,
                'stock_import_id' => $import4->id,
                'ingredient_id' => $detail['ingredient']->id,
                'created_by' => $employee->id,
                'updated_by' => $employee->id,
            ]);

            $detail['ingredient']->increment('current_stock', $detail['received']);
            $totalAmount4 += $totalPrice;
        }

        $import4->update(['total_amount' => $totalAmount4]);

        // Nhập 5: Đồ uống (1 ngày trước)
        $import5 = StockImport::create([
            'import_date' => $now->copy()->subDays(1),
            'total_amount' => 0,
            'supplier_id' => $suppliers[3]->id,
            'created_by' => $employee->id,
            'updated_by' => $employee->id,
        ]);

        $import5Details = [
            ['ingredient' => $ingredients[27], 'ordered' => 50, 'received' => 50, 'unit_price' => 35000], // Nước cam ép
            ['ingredient' => $ingredients[28], 'ordered' => 25, 'received' => 25, 'unit_price' => 280000], // Cà phê
            ['ingredient' => $ingredients[29], 'ordered' => 15, 'received' => 15, 'unit_price' => 120000], // Trà
        ];

        $totalAmount5 = 0;
        foreach ($import5Details as $detail) {
            $totalPrice = $detail['received'] * $detail['unit_price'];
            StockImportDetail::create([
                'ordered_quantity' => $detail['ordered'],
                'received_quantity' => $detail['received'],
                'unit_price' => $detail['unit_price'],
                'total_price' => $totalPrice,
                'stock_import_id' => $import5->id,
                'ingredient_id' => $detail['ingredient']->id,
                'created_by' => $employee->id,
                'updated_by' => $employee->id,
            ]);

            $detail['ingredient']->increment('current_stock', $detail['received']);
            $totalAmount5 += $totalPrice;
        }

        $import5->update(['total_amount' => $totalAmount5]);
    }

    /**
     * Tạo phiếu xuất kho kèm chi tiết
     */
    private function createStockExports($ingredients, $employee, $now): void
    {
        // Xuất 1: Cho hoạt động bếp (4 ngày trước) - ĐÃ HOÀN TẤT
        $export1 = StockExport::create([
            'export_date' => $now->copy()->subDays(4),
            'purpose' => 'Hoạt động bếp hàng ngày',
            'status' => StockExport::STATUS_COMPLETED,
            'created_by' => $employee->id,
            'updated_by' => $employee->id,
        ]);

        $export1Details = [
            ['ingredient' => $ingredients[0], 'quantity' => 10], // Cà chua
            ['ingredient' => $ingredients[1], 'quantity' => 12], // Hành tây
            ['ingredient' => $ingredients[8], 'quantity' => 15], // Ức gà
            ['ingredient' => $ingredients[9], 'quantity' => 8], // Thịt bò
            ['ingredient' => $ingredients[13], 'quantity' => 15], // Sữa tươi
            ['ingredient' => $ingredients[17], 'quantity' => 30], // Gạo
        ];

        foreach ($export1Details as $detail) {
            StockExportDetail::create([
                'quantity' => $detail['quantity'],
                'ingredient_id' => $detail['ingredient']->id,
                'stock_export_id' => $export1->id,
                'created_by' => $employee->id,
                'updated_by' => $employee->id,
            ]);

            // Giảm tồn kho vì đã hoàn tất
            $detail['ingredient']->decrement('current_stock', $detail['quantity']);
        }

        // Xuất 2: Cho sự kiện tiệc cưới (2 ngày trước) - ĐÃ HOÀN TẤT
        $export2 = StockExport::create([
            'export_date' => $now->copy()->subDays(2),
            'purpose' => 'Sự kiện tiệc cưới',
            'status' => StockExport::STATUS_COMPLETED,
            'created_by' => $employee->id,
            'updated_by' => $employee->id,
        ]);

        $export2Details = [
            ['ingredient' => $ingredients[8], 'quantity' => 20], // Ức gà
            ['ingredient' => $ingredients[11], 'quantity' => 12], // Cá hồi
            ['ingredient' => $ingredients[12], 'quantity' => 15], // Tôm sú
            ['ingredient' => $ingredients[3], 'quantity' => 10], // Cà rốt
            ['ingredient' => $ingredients[18], 'quantity' => 20], // Mì Ý
        ];

        foreach ($export2Details as $detail) {
            StockExportDetail::create([
                'quantity' => $detail['quantity'],
                'ingredient_id' => $detail['ingredient']->id,
                'stock_export_id' => $export2->id,
                'created_by' => $employee->id,
                'updated_by' => $employee->id,
            ]);

            $detail['ingredient']->decrement('current_stock', $detail['quantity']);
        }

        // Xuất 3: Đã duyệt nhưng chưa hoàn tất (hôm nay)
        $export3 = StockExport::create([
            'export_date' => $now->copy(),
            'purpose' => 'Chuẩn bị thực đơn cuối tuần đặc biệt',
            'status' => StockExport::STATUS_APPROVED,
            'created_by' => $employee->id,
            'updated_by' => $employee->id,
        ]);

        $export3Details = [
            ['ingredient' => $ingredients[9], 'quantity' => 10], // Thịt bò
            ['ingredient' => $ingredients[10], 'quantity' => 15], // Thịt heo
            ['ingredient' => $ingredients[5], 'quantity' => 5], // Xà lách
        ];

        foreach ($export3Details as $detail) {
            StockExportDetail::create([
                'quantity' => $detail['quantity'],
                'ingredient_id' => $detail['ingredient']->id,
                'stock_export_id' => $export3->id,
                'created_by' => $employee->id,
                'updated_by' => $employee->id,
            ]);
            // Chưa giảm tồn kho - chỉ khi trạng thái thành COMPLETED
        }

        // Xuất 4: Nháp (tương lai)
        $export4 = StockExport::create([
            'export_date' => $now->copy()->addDays(1),
            'purpose' => 'Chuẩn bị thực đơn tuần sau - bản nháp',
            'status' => StockExport::STATUS_DRAFT,
            'created_by' => $employee->id,
            'updated_by' => $employee->id,
        ]);

        $export4Details = [
            ['ingredient' => $ingredients[16], 'quantity' => 20], // Trứng gà
            ['ingredient' => $ingredients[14], 'quantity' => 5], // Bơ
            ['ingredient' => $ingredients[19], 'quantity' => 10], // Bột mì
        ];

        foreach ($export4Details as $detail) {
            StockExportDetail::create([
                'quantity' => $detail['quantity'],
                'ingredient_id' => $detail['ingredient']->id,
                'stock_export_id' => $export4->id,
                'created_by' => $employee->id,
                'updated_by' => $employee->id,
            ]);
        }
    }

    /**
     * Tạo phiếu hao hụt
     */
    private function createStockLosses($ingredients, $employee, $now): void
    {
        $losses = [
            [
                'ingredient' => $ingredients[0], // Cà chua
                'quantity' => 2,
                'reason' => 'Hết hạn sử dụng - quá ngày tốt nhất',
                'loss_date' => $now->copy()->subDays(3),
            ],
            [
                'ingredient' => $ingredients[13], // Sữa tươi
                'quantity' => 5,
                'reason' => 'Hư hỏng - tủ lạnh bị sự cố',
                'loss_date' => $now->copy()->subDays(2),
            ],
            [
                'ingredient' => $ingredients[9], // Thịt bò
                'quantity' => 2,
                'reason' => 'Hư hại trong quá trình xử lý',
                'loss_date' => $now->copy()->subDays(1),
            ],
            [
                'ingredient' => $ingredients[16], // Trứng gà
                'quantity' => 3,
                'reason' => 'Vỡ trong quá trình vận chuyển',
                'loss_date' => $now->copy()->subDays(1),
            ],
        ];

        foreach ($losses as $lossData) {
            StockLoss::create([
                'quantity' => $lossData['quantity'],
                'reason' => $lossData['reason'],
                'loss_date' => $lossData['loss_date'],
                'employee_id' => $employee->id,
                'ingredient_id' => $lossData['ingredient']->id,
                'created_by' => $employee->id,
                'updated_by' => $employee->id,
            ]);

            // Giảm tồn kho
            $lossData['ingredient']->decrement('current_stock', $lossData['quantity']);
        }
    }
}
