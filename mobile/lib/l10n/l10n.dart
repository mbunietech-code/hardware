import 'sw.dart';

/// Very small translation layer: English text is the key, Swahili lives in [swahili].
/// `{name}` placeholders are filled from [params].
class L10n {
  static const supported = {'en': 'English', 'sw': 'Kiswahili'};

  static String lang = 'en';

  static bool get isSwahili => lang == 'sw';
}

String tr(String english, [Map<String, Object?> params = const {}]) {
  var text = L10n.isSwahili ? (swahili[english] ?? english) : english;
  params.forEach((k, v) => text = text.replaceAll('{$k}', '${v ?? ''}'));
  return text;
}
