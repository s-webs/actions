<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Проект без исключения на русском интерфейсе (APP_LOCALE=ru), но Laravel
    | 11+/12 не публикует lang/* по умолчанию — без этого файла автосообщения
    | валидатора (когда правило не переопределено вручную через
    | ValidationException::withMessages()) выводились как сырой ключ
    | ("validation.required") вместо текста. Большинство контроллеров этого
    | проекта пишут кастомные сообщения сами, но не все — например,
    | ApprovalController::reject()/rework() полагаются на дефолтное сообщение
    | правила `required` для `review_comment`.
    |
    */

    'accepted' => 'Поле «:attribute» должно быть принято.',
    'accepted_if' => 'Поле «:attribute» должно быть принято, когда :other равно :value.',
    'active_url' => 'Поле «:attribute» должно быть корректным URL.',
    'after' => 'Поле «:attribute» должно содержать дату после :date.',
    'after_or_equal' => 'Поле «:attribute» должно содержать дату после :date или равную ей.',
    'alpha' => 'Поле «:attribute» должно содержать только буквы.',
    'alpha_dash' => 'Поле «:attribute» должно содержать только буквы, цифры, дефисы и подчёркивания.',
    'alpha_num' => 'Поле «:attribute» должно содержать только буквы и цифры.',
    'any_of' => 'Поле «:attribute» недопустимо.',
    'array' => 'Поле «:attribute» должно быть массивом.',
    'ascii' => 'Поле «:attribute» должно содержать только однобайтовые буквенно-цифровые символы и знаки.',
    'before' => 'Поле «:attribute» должно содержать дату до :date.',
    'before_or_equal' => 'Поле «:attribute» должно содержать дату до :date или равную ей.',
    'between' => [
        'array' => 'В поле «:attribute» должно быть от :min до :max элементов.',
        'file' => 'Размер поля «:attribute» должен быть от :min до :max килобайт.',
        'numeric' => 'Значение поля «:attribute» должно быть от :min до :max.',
        'string' => 'Длина поля «:attribute» должна быть от :min до :max символов.',
    ],
    'boolean' => 'Поле «:attribute» должно быть true или false.',
    'can' => 'Поле «:attribute» содержит недопустимое значение.',
    'confirmed' => 'Подтверждение поля «:attribute» не совпадает.',
    'contains' => 'В поле «:attribute» отсутствует обязательное значение.',
    'current_password' => 'Неверный пароль.',
    'date' => 'Поле «:attribute» должно быть корректной датой.',
    'date_equals' => 'Поле «:attribute» должно быть датой, равной :date.',
    'date_format' => 'Поле «:attribute» должно соответствовать формату :format.',
    'decimal' => 'Поле «:attribute» должно содержать :decimal знаков после запятой.',
    'declined' => 'Поле «:attribute» должно быть отклонено.',
    'declined_if' => 'Поле «:attribute» должно быть отклонено, когда :other равно :value.',
    'different' => 'Поля «:attribute» и «:other» должны различаться.',
    'digits' => 'Поле «:attribute» должно содержать :digits цифр.',
    'digits_between' => 'Поле «:attribute» должно содержать от :min до :max цифр.',
    'dimensions' => 'Поле «:attribute» имеет недопустимые размеры изображения.',
    'distinct' => 'Поле «:attribute» содержит повторяющееся значение.',
    'doesnt_contain' => 'Поле «:attribute» не должно содержать: :values.',
    'doesnt_end_with' => 'Поле «:attribute» не должно заканчиваться на одно из: :values.',
    'doesnt_start_with' => 'Поле «:attribute» не должно начинаться с одного из: :values.',
    'email' => 'Поле «:attribute» должно быть корректным email-адресом.',
    'encoding' => 'Поле «:attribute» должно быть в кодировке :encoding.',
    'ends_with' => 'Поле «:attribute» должно заканчиваться на одно из: :values.',
    'enum' => 'Выбранное значение поля «:attribute» недопустимо.',
    'exists' => 'Выбранное значение поля «:attribute» недопустимо.',
    'extensions' => 'Поле «:attribute» должно иметь одно из расширений: :values.',
    'file' => 'Поле «:attribute» должно быть файлом.',
    'filled' => 'Поле «:attribute» обязательно должно иметь значение.',
    'gt' => [
        'array' => 'В поле «:attribute» должно быть больше :value элементов.',
        'file' => 'Размер поля «:attribute» должен быть больше :value килобайт.',
        'numeric' => 'Значение поля «:attribute» должно быть больше :value.',
        'string' => 'Длина поля «:attribute» должна быть больше :value символов.',
    ],
    'gte' => [
        'array' => 'В поле «:attribute» должно быть :value элементов или больше.',
        'file' => 'Размер поля «:attribute» должен быть больше или равен :value килобайт.',
        'numeric' => 'Значение поля «:attribute» должно быть больше или равно :value.',
        'string' => 'Длина поля «:attribute» должна быть больше или равна :value символов.',
    ],
    'hex_color' => 'Поле «:attribute» должно быть корректным HEX-цветом.',
    'image' => 'Поле «:attribute» должно быть изображением.',
    'in' => 'Выбранное значение поля «:attribute» недопустимо.',
    'in_array' => 'Поле «:attribute» должно присутствовать в :other.',
    'in_array_keys' => 'Поле «:attribute» должно содержать хотя бы один из ключей: :values.',
    'integer' => 'Поле «:attribute» должно быть целым числом.',
    'ip' => 'Поле «:attribute» должно быть корректным IP-адресом.',
    'ipv4' => 'Поле «:attribute» должно быть корректным IPv4-адресом.',
    'ipv6' => 'Поле «:attribute» должно быть корректным IPv6-адресом.',
    'json' => 'Поле «:attribute» должно быть корректной JSON-строкой.',
    'list' => 'Поле «:attribute» должно быть списком.',
    'lowercase' => 'Поле «:attribute» должно быть в нижнем регистре.',
    'lt' => [
        'array' => 'В поле «:attribute» должно быть меньше :value элементов.',
        'file' => 'Размер поля «:attribute» должен быть меньше :value килобайт.',
        'numeric' => 'Значение поля «:attribute» должно быть меньше :value.',
        'string' => 'Длина поля «:attribute» должна быть меньше :value символов.',
    ],
    'lte' => [
        'array' => 'В поле «:attribute» не должно быть больше :value элементов.',
        'file' => 'Размер поля «:attribute» должен быть меньше или равен :value килобайт.',
        'numeric' => 'Значение поля «:attribute» должно быть меньше или равно :value.',
        'string' => 'Длина поля «:attribute» должна быть меньше или равна :value символов.',
    ],
    'mac_address' => 'Поле «:attribute» должно быть корректным MAC-адресом.',
    'max' => [
        'array' => 'В поле «:attribute» не должно быть больше :max элементов.',
        'file' => 'Размер поля «:attribute» не должен превышать :max килобайт.',
        'numeric' => 'Значение поля «:attribute» не должно превышать :max.',
        'string' => 'Длина поля «:attribute» не должна превышать :max символов.',
    ],
    'max_digits' => 'Поле «:attribute» не должно содержать больше :max цифр.',
    'mimes' => 'Поле «:attribute» должно быть файлом одного из типов: :values.',
    'mimetypes' => 'Поле «:attribute» должно быть файлом одного из типов: :values.',
    'min' => [
        'array' => 'В поле «:attribute» должно быть не менее :min элементов.',
        'file' => 'Размер поля «:attribute» должен быть не менее :min килобайт.',
        'numeric' => 'Значение поля «:attribute» должно быть не менее :min.',
        'string' => 'Длина поля «:attribute» должна быть не менее :min символов.',
    ],
    'min_digits' => 'Поле «:attribute» должно содержать не менее :min цифр.',
    'missing' => 'Поле «:attribute» должно отсутствовать.',
    'missing_if' => 'Поле «:attribute» должно отсутствовать, когда :other равно :value.',
    'missing_unless' => 'Поле «:attribute» должно отсутствовать, если :other не равно :value.',
    'missing_with' => 'Поле «:attribute» должно отсутствовать, когда присутствует :values.',
    'missing_with_all' => 'Поле «:attribute» должно отсутствовать, когда присутствуют :values.',
    'multiple_of' => 'Значение поля «:attribute» должно быть кратно :value.',
    'not_in' => 'Выбранное значение поля «:attribute» недопустимо.',
    'not_regex' => 'Формат поля «:attribute» недопустим.',
    'numeric' => 'Поле «:attribute» должно быть числом.',
    'password' => [
        'letters' => 'Поле «:attribute» должно содержать хотя бы одну букву.',
        'mixed' => 'Поле «:attribute» должно содержать хотя бы одну заглавную и одну строчную букву.',
        'numbers' => 'Поле «:attribute» должно содержать хотя бы одну цифру.',
        'symbols' => 'Поле «:attribute» должно содержать хотя бы один символ.',
        'uncompromised' => 'Указанное значение поля «:attribute» встречается в утечках данных. Выберите другое значение.',
    ],
    'present' => 'Поле «:attribute» должно присутствовать.',
    'present_if' => 'Поле «:attribute» должно присутствовать, когда :other равно :value.',
    'present_unless' => 'Поле «:attribute» должно присутствовать, если :other не равно :value.',
    'present_with' => 'Поле «:attribute» должно присутствовать, когда присутствует :values.',
    'present_with_all' => 'Поле «:attribute» должно присутствовать, когда присутствуют :values.',
    'prohibited' => 'Поле «:attribute» запрещено.',
    'prohibited_if' => 'Поле «:attribute» запрещено, когда :other равно :value.',
    'prohibited_if_accepted' => 'Поле «:attribute» запрещено, когда :other принято.',
    'prohibited_if_declined' => 'Поле «:attribute» запрещено, когда :other отклонено.',
    'prohibited_unless' => 'Поле «:attribute» запрещено, если :other не входит в :values.',
    'prohibits' => 'Поле «:attribute» запрещает присутствие :other.',
    'regex' => 'Формат поля «:attribute» недопустим.',
    'required' => 'Поле «:attribute» обязательно для заполнения.',
    'required_array_keys' => 'Поле «:attribute» должно содержать элементы: :values.',
    'required_if' => 'Поле «:attribute» обязательно, когда :other равно :value.',
    'required_if_accepted' => 'Поле «:attribute» обязательно, когда :other принято.',
    'required_if_declined' => 'Поле «:attribute» обязательно, когда :other отклонено.',
    'required_unless' => 'Поле «:attribute» обязательно, если :other не входит в :values.',
    'required_with' => 'Поле «:attribute» обязательно, когда присутствует :values.',
    'required_with_all' => 'Поле «:attribute» обязательно, когда присутствуют :values.',
    'required_without' => 'Поле «:attribute» обязательно, когда :values отсутствует.',
    'required_without_all' => 'Поле «:attribute» обязательно, когда ни одно из :values не присутствует.',
    'same' => 'Поле «:attribute» должно совпадать с «:other».',
    'size' => [
        'array' => 'Поле «:attribute» должно содержать :size элементов.',
        'file' => 'Размер поля «:attribute» должен быть :size килобайт.',
        'numeric' => 'Значение поля «:attribute» должно быть :size.',
        'string' => 'Длина поля «:attribute» должна быть :size символов.',
    ],
    'starts_with' => 'Поле «:attribute» должно начинаться с одного из: :values.',
    'string' => 'Поле «:attribute» должно быть строкой.',
    'timezone' => 'Поле «:attribute» должно быть корректным часовым поясом.',
    'unique' => 'Такое значение поля «:attribute» уже используется.',
    'uploaded' => 'Не удалось загрузить файл «:attribute».',
    'uppercase' => 'Поле «:attribute» должно быть в верхнем регистре.',
    'url' => 'Поле «:attribute» должно быть корректным URL.',
    'ulid' => 'Поле «:attribute» должно быть корректным ULID.',
    'uuid' => 'Поле «:attribute» должно быть корректным UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'custom' => [],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | Человекочитаемые названия полей вместо snake_case — только те, что
    | реально фигурируют в $request->validate() по всему проекту.
    |
    */

    'attributes' => [
        'name' => 'имя',
        'email' => 'email',
        'login' => 'логин',
        'password' => 'пароль',
        'current_password' => 'текущий пароль',
        'password_confirmation' => 'подтверждение пароля',
        'token' => 'токен',
        'approved_percent' => '% готовности',
        'review_comment' => 'комментарий',
        'measure_status' => 'статус мероприятия',
        'risk_text' => 'риск / проблема',
        'needs_decision' => 'требуется решение руководства',
        'done_text' => 'что сделано за период',
        'submitted_by_name' => 'ФИО и должность',
        'title' => 'название',
        'files' => 'файлы',
        'url' => 'ссылка',
        'planned_date' => 'плановая дата',
        'weight' => 'вес',
        'expires_at' => 'срок действия',
        'file' => 'файл',
    ],

];
