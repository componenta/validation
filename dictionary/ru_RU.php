<?php

return [
    // Basic
    'validation.required' => 'Это поле обязательно для заполнения.',
    'validation.filled' => 'Это поле должно быть заполнено.',
    'validation.in' => 'Значение должно быть одним из: :values.',
    'validation.not_in' => 'Значение не должно быть одним из: :values.',

    // Type checks
    'validation.is_string.not_string' => 'Значение должно быть строкой, получен :type.',
    'validation.is_int.not_int' => 'Значение должно быть целым числом.',
    'validation.is_array.not_array' => 'Значение должно быть массивом.',
    'validation.is_array.not_list' => 'Значение должно быть списком.',
    'validation.numeric.not_numeric' => 'Значение должно быть числом.',

    // String
    'validation.email.not_string' => 'Электронная почта должна быть строкой, получен :type.',
    'validation.email.invalid' => 'Некорректный адрес электронной почты',
    'validation.length.not_string' => 'Значение должно быть строкой, получен :type.',
    'validation.length.too_short' => 'Должно содержать минимум :min символов.',
    'validation.length.too_long' => 'Должно содержать максимум :max символов.',
    'validation.length.out_of_range' => 'Длина должна быть от :min до :max символов.',
    'validation.length.exact' => 'Должно содержать ровно :expected символов.',
    'validation.regex.not_string' => 'Значение должно быть строкой, получен :type.',
    'validation.regex.no_match' => 'Формат значения некорректен.',
    'validation.url.not_string' => 'Значение должно быть строкой, получен :type.',
    'validation.url.invalid' => 'URL некорректен.',
    'validation.url.invalid_protocol' => 'URL должен использовать один из протоколов: :protocols.',
    'validation.uuid.not_string' => 'Значение должно быть строкой, получен :type.',
    'validation.uuid.invalid' => 'Значение не является корректным UUID.',
    'validation.uuid.invalid_version' => 'Ожидается UUID версии :expected, получена версия :actual.',
    'validation.alpha.not_string' => 'Значение должно быть строкой, получен :type.',
    'validation.alpha.invalid' => 'Значение может содержать только буквы.',
    'validation.alpha_numeric.not_string' => 'Значение должно быть строкой, получен :type.',
    'validation.alpha_numeric.invalid' => 'Значение может содержать только буквы и цифры.',
    'validation.alpha_dash.not_string' => 'Значение должно быть строкой, получен :type.',
    'validation.alpha_dash.invalid' => 'Значение может содержать только буквы, цифры, дефисы и подчёркивания.',
    'validation.phone.not_string' => 'Номер телефона должен быть строкой, получен :type.',
    'validation.phone.invalid' => 'Номер телефона некорректен для формата :region.',
    'validation.phone.invalid_region' => 'Номер телефона должен принадлежать региону :region, но обнаружен :actual_region.',

    // Numeric / Range
    'validation.range.not_numeric' => 'Значение должно быть числом, получен :type.',
    'validation.range.too_small' => 'Значение должно быть не менее :min.',
    'validation.range.too_large' => 'Значение не должно превышать :max.',
    'validation.range.out_of_range' => 'Значение должно быть между :min и :max.',
    'validation.range.invalid_date' => 'Значение должно быть корректной датой.',
    'validation.positive.not_numeric' => 'Значение должно быть числом, получен :type.',
    'validation.positive.not_positive' => 'Значение должно быть положительным.',
    'validation.negative.not_numeric' => 'Значение должно быть числом, получен :type.',
    'validation.negative.not_negative' => 'Значение должно быть отрицательным.',

    // Comparison
    'validation.equals.not_equal' => 'Значение должно совпадать с :other.',
    'validation.not_equals.equal' => 'Значение должно отличаться от :other.',
    'validation.greater_than.not_comparable' => 'Невозможно сравнить значения с :other.',
    'validation.greater_than.not_greater' => 'Значение должно быть больше :or_equal :other.',
    'validation.less_than.not_comparable' => 'Невозможно сравнить значения с :other.',
    'validation.less_than.not_less' => 'Значение должно быть меньше :or_equal :other.',
    'validation.confirmed.not_match' => 'Значение не совпадает с :confirmation.',

    // Date
    'validation.date.invalid' => 'Значение должно быть корректной датой.',
    'validation.date_format.not_string' => 'Значение должно быть строкой, получен :type.',
    'validation.date_format.invalid_format' => 'Дата должна соответствовать формату :format.',
    'validation.before.invalid_date' => 'Значение должно быть корректной датой.',
    'validation.before.not_before' => 'Дата должна быть до:or_equal :date.',
    'validation.after.invalid_date' => 'Значение должно быть корректной датой.',
    'validation.after.not_after' => 'Дата должна быть после:or_equal :date.',

    // Array
    'validation.count.not_countable' => 'Значение должно быть массивом или countable, получен :type.',
    'validation.count.too_few' => 'Должно содержать минимум :min элементов.',
    'validation.count.too_many' => 'Должно содержать максимум :max элементов.',
    'validation.count.out_of_range' => 'Должно содержать от :min до :max элементов.',
    'validation.count.exact' => 'Должно содержать ровно :expected элементов.',
    'validation.distinct.not_array' => 'Значение должно быть массивом.',
    'validation.distinct.duplicates' => 'Обнаружены дублирующиеся значения: :duplicates.',
    'validation.array_of.not_array' => 'Значение должно быть массивом.',

    // Conditional
    'validation.required_if' => 'Это поле обязательно, потому что ":field" равно ":value".',
    'validation.required_with' => 'Это поле обязательно, когда присутствует :fields.',
    'validation.required_without' => 'Это поле обязательно, когда отсутствует :fields.',
    'validation.prohibited_if' => 'Это поле запрещено, когда :field равно :value.',

    // Password
    'validation.password.not_string' => 'Пароль должен быть строкой, получен :type.',
    'validation.password.too_short' => 'Пароль должен содержать минимум :min символов.',
    'validation.password.missing_upper' => 'Пароль должен содержать хотя бы одну заглавную букву.',
    'validation.password.missing_lower' => 'Пароль должен содержать хотя бы одну строчную букву.',
    'validation.password.missing_digit' => 'Пароль должен содержать хотя бы одну цифру.',
    'validation.password.missing_special' => 'Пароль должен содержать хотя бы один специальный символ.',
    'validation.password.not_confirmed' => 'Подтверждение пароля не совпадает.',

    // Database
    'validation.exists' => 'Выбранное значение для :attribute не существует.',
    'validation.unique' => 'Значение :attribute уже занято.',

    'validation.accepted.not_accepted' => 'Необходимо подтвердить согласие.',

    // File
    'validation.file_size.not_uploaded_file' => 'Значение должно быть загруженным файлом, получен :type.',
    'validation.file_size.too_large' => 'Размер файла не должен превышать :max, получен :actual.',
    'validation.file_size.too_small' => 'Размер файла должен быть не менее :min, получен :actual.',

    'validation.mime_type.not_uploaded_file' => 'Значение должно быть загруженным файлом, получен :type.',
    'validation.mime_type.invalid' => 'Тип файла :actual не допускается. Допустимые типы: :allowed.',
];