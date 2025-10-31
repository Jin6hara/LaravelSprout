        {{-- 基本情報 JSインライン編集--}}
        <div class="card mt-4">
            <div class="card-header">
                <h4>基本情報</h4>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <colgroup>
                        <col style="width:140px;"> <!-- ← 好きな幅に -->
                        <col>
                    </colgroup>
                    <th>Email</th>
                    <td>
                        <span id="email_display">{{ $user->email }}</span>
                        <input type="text" id="email_input" class="form-control d-none" value="{{ $user->email }}">
                    </td>
                    <td style="width: 100px; text-align: center;">
                        <button class="btn btn-sm btn-outline-secondary" id="email_edit" onclick="editField('email')">✏️</button>
                        <button class="btn btn-sm btn-primary d-none" id="email_save" onclick="saveField('email')">✅</button>
                        <button class="btn btn-sm btn-danger d-none" id="email_cancel" onclick="cancelField('email')">❌</button>
                    </td>
                    </tr>
                    <tr>
                        <th>電話番号</th>
                        <td>
                            <span id="phone_number_display">{{ $user->phone_number }}</span>
                            <input type="text" id="phone_number_input" class="form-control d-none" value="{{ $user->phone_number }}">
                        </td>
                        <td style="width: 100px; text-align: center;">
                            <button class="btn btn-sm btn-outline-secondary" id="phone_number_edit" onclick="editField('phone_number')">✏️</button>
                            <button class="btn btn-sm btn-primary d-none" id="phone_number_save" onclick="saveField('phone_number')">✅</button>
                            <button class="btn btn-sm btn-danger d-none" id="phone_number_cancel" onclick="cancelField('phone_number')">❌</button>
                        </td>
                    </tr>
                    <tr>
                        <th>住所</th>
                        <td>
                            <span id="address_display">{{ $user->address }}</span>
                            <input type="text" id="address_input" class="form-control d-none" value="{{ $user->address }}">
                        </td>
                        <td style="width: 100px; text-align: center;">
                            <button class="btn btn-sm btn-outline-secondary" id="address_edit" onclick="editField('address')">✏️</button>
                            <button class="btn btn-sm btn-primary d-none" id="address_save" onclick="saveField('address')">✅</button>
                            <button class="btn btn-sm btn-danger d-none" id="address_cancel" onclick="cancelField('address')">❌</button>
                        </td>
                    </tr>
                    <tr>
                        <th>備考</th>
                        <td>
                            <span id="note_display">{{ $user->note }}</span>
                            <input type="text" id="note_input" class="form-control d-none" value="{{ $user->note }}">
                        </td>
                        <td style="width: 100px; text-align: center;">
                            <button class="btn btn-sm btn-outline-secondary" id="note_edit" onclick="editField('note')">✏️</button>
                            <button class="btn btn-sm btn-primary d-none" id="note_save" onclick="saveField('note')">✅</button>
                            <button class="btn btn-sm btn-danger d-none" id="note_cancel" onclick="cancelField('note')">❌</button>
                        </td>
                    </tr>
                </table>
            </div>
        </div>