import { IonText } from '@ionic/react';

export default function FieldError({ message }: { message?: string | null }) {
  if (!message) {
    return null;
  }

  return (
    <IonText color="danger" className="field-error">
      {message}
    </IonText>
  );
}
