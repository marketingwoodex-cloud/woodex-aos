import { Link } from 'react-router-dom';
import { Button, Card, EmptyState } from '../components/ui.jsx';

export default function NotFound() {
  return (
    <Card>
      <EmptyState
        icon="inbox"
        title="Page not found"
        description="That route is not part of the Woodex workspace. Head back to the dashboard to keep going."
        action={
          <Link to="/dashboard">
            <Button variant="primary">Back to dashboard</Button>
          </Link>
        }
      />
    </Card>
  );
}
