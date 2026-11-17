<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('apphub')->create('warehouse_check_manual_photos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('document_type', 12);
            $table->unsignedBigInteger('check_header_id');
            $table->unsignedBigInteger('check_detail_id');
            $table->string('item_code', 100);
            $table->decimal('qty_base', 28, 4);
            $table->unsignedBigInteger('checker_user_id');
            $table->string('file_path', 500);
            $table->string('mime_type', 30);
            $table->dateTime('created_at');
            $table->index(['document_type', 'check_header_id', 'check_detail_id'], 'wc_manual_photo_doc_line');
        });
    }

    public function down(): void
    {
        Schema::connection('apphub')->dropIfExists('warehouse_check_manual_photos');
    }
};
