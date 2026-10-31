<div data-option-editor>
<div class="store-option-rows" data-option-rows hidden></div>
<button type="button" class="btn btn-default" data-option-add hidden>+ Thêm giá trị</button>
<p class="help" data-option-count aria-live="polite"></p>
<label for="values_{{ $valueKey }}">Giá trị (mỗi dòng một giá trị)</label>
<textarea id="values_{{ $valueKey }}" data-option-source name="values" required @if($retry && !$errors->has('name') && !$errors->has('sort_order') && ($errors->has('values') || $errors->has('values.*'))) autofocus aria-invalid="true" @endif>{{ $optionText }}</textarea>
<button type="button" class="btn btn-default" data-option-toggle hidden>Nhập nhiều dòng</button>
<p class="help">Tối đa100 giá trị/nhóm. Giá trị đang dùng bởi SKU không được xóa/đổi tên. Nhấn Lưu nhóm để cập nhật các tổ hợp đã lưu.</p>
</div>
