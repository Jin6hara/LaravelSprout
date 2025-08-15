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
                        <th>自己紹介</th>
                        <td>
                            <span id="self_introduction_display">{{ $user->self_introduction }}</span>
                            <input type="text" id="self_introduction_input" class="form-control d-none" value="{{ $user->self_introduction }}">
                        </td>
                        <td style="width: 100px; text-align: center;">
                            <button class="btn btn-sm btn-outline-secondary" id="self_introduction_edit" onclick="editField('self_introduction')">✏️</button>
                            <button class="btn btn-sm btn-primary d-none" id="self_introduction_save" onclick="saveField('self_introduction')">✅</button>
                            <button class="btn btn-sm btn-danger d-none" id="self_introduction_cancel" onclick="cancelField('self_introduction')">❌</button>
                        </td>
                    </tr>
                </table>
            </div>
        </div>